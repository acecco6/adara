<?php

namespace Tests\Feature;

use App\Livewire\Products\Detail;
use App\Models\ProductVariant;
use App\Services\InitialStockService;
use App\Services\StockAdjustmentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class InitialStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_initial_quantity_and_real_cost_once_with_calculated_price(): void
    {
        $variant = ProductVariant::factory()->create();
        $service = app(InitialStockService::class);

        $operationId = $service->record($variant->id, '7', '1234,5678', 'initial-1');

        $this->assertSame($operationId, $service->record($variant->id, '7', '1234.5678', 'initial-1'));
        $this->assertDatabaseHas('stock_balances', ['product_variant_id' => $variant->id, 'quantity_on_hand' => 7]);
        $this->assertDatabaseHas('stock_operations', ['id' => $operationId, 'kind' => 'initial_stock']);
        $this->assertDatabaseHas('stock_movements', [
            'stock_operation_id' => $operationId,
            'product_variant_id' => $variant->id,
            'quantity_delta' => 7,
            'quantity_after' => 7,
            'unit_cost_10000' => 12345678,
        ]);
        $this->assertSame(12345678, $variant->fresh()->current_unit_cost_10000);
        $this->assertSame(230000, $variant->fresh()->current_price_cents);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_initial_price_uses_particular_markup_when_present(): void
    {
        $variant = ProductVariant::factory()->create(['markup_override_decimal' => '50']);
        app(InitialStockService::class)->record($variant->id, '1', '10000', 'initial-particular');

        $this->assertSame(1500000, $variant->fresh()->current_price_cents);
        $this->assertSame('80', DB::table('pricing_settings')->where('id', 1)->value('global_markup_decimal'));
    }

    public function test_rejects_a_second_initial_load_and_reused_key_with_different_data(): void
    {
        $variant = ProductVariant::factory()->create();
        $service = app(InitialStockService::class);
        $service->record($variant->id, '2', '10', 'initial-1');

        foreach ([['3', '10', 'initial-1', InvalidArgumentException::class], ['2', '10', 'initial-2', DomainException::class]] as [$quantity, $cost, $key, $exceptionClass]) {
            try {
                $service->record($variant->id, $quantity, $cost, $key);
                $this->fail('La segunda carga debía rechazarse.');
            } catch (InvalidArgumentException|DomainException $exception) {
                $this->assertInstanceOf($exceptionClass, $exception);
            }
        }

        $this->assertDatabaseCount('stock_operations', 1);
        $this->assertDatabaseHas('stock_balances', ['product_variant_id' => $variant->id, 'quantity_on_hand' => 2]);
    }

    public function test_initial_load_requires_unmodified_active_variant_and_valid_inputs(): void
    {
        $variant = ProductVariant::factory()->create();
        $service = app(InitialStockService::class);

        foreach ([['0', '10'], ['2', '0'], ['1.5', '10'], ['2', '1,23456']] as [$quantity, $cost]) {
            try {
                $service->record($variant->id, $quantity, $cost, 'invalid-1');
                $this->fail('La entrada inválida debía rechazarse.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }

        app(StockAdjustmentService::class)->adjust($variant->id, 1, 'Ingreso previo', 'prior-1');
        app(StockAdjustmentService::class)->adjust($variant->id, -1, 'Salida previa', 'prior-2');
        $this->expectException(DomainException::class);
        $service->record($variant->id, '2', '10', 'after-movement');
    }

    public function test_archived_variant_cannot_receive_initial_stock(): void
    {
        $variant = ProductVariant::factory()->create(['is_active' => false]);

        $this->expectException(DomainException::class);
        app(InitialStockService::class)->record($variant->id, '2', '10', 'archived-initial');
    }

    public function test_livewire_initial_adjustment_and_minimum_flow_preserves_cost(): void
    {
        $variant = ProductVariant::factory()->create();

        Livewire::test(Detail::class, ['groupId' => $variant->product_group_id])
            ->call('openInitialStock', $variant->id)
            ->set('initialQuantity', '5')
            ->set('initialCost', '234,5000')
            ->call('saveInitialStock')
            ->assertHasNoErrors()
            ->assertSee('$ 500,00')
            ->call('openMinimum', $variant->id)
            ->set('minimumQuantity', '4')
            ->call('saveMinimum')
            ->assertHasNoErrors()
            ->call('openAdjustment', $variant->id)
            ->set('adjustmentDirection', 'out')
            ->set('adjustmentQuantity', '1')
            ->set('adjustmentReason', 'Muestra gratuita')
            ->call('saveAdjustment')
            ->assertHasNoErrors()
            ->assertSee('En mínimo o debajo');

        $this->assertSame(4, $variant->fresh()->balance->quantity_on_hand);
        $this->assertSame(4, $variant->fresh()->minimum_stock);
        $this->assertSame(2345000, $variant->fresh()->current_unit_cost_10000);
        $this->assertSame(50000, $variant->fresh()->current_price_cents);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_minimum_cannot_be_negative_or_fractional(): void
    {
        $variant = ProductVariant::factory()->create();
        $service = app(InitialStockService::class);
        $service->setMinimum($variant->id, '3');
        $this->assertSame(3, $variant->fresh()->minimum_stock);

        $this->expectException(InvalidArgumentException::class);
        $service->setMinimum($variant->id, '-1');
    }
}
