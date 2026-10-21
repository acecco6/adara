<?php

namespace Tests\Feature;

use App\Models\ProductGroup;
use App\Models\ProductVariant;
use App\Services\StockAdjustmentService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class StockAdjustmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_variant_starts_with_zero_stock_and_no_invented_cost(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->assertDatabaseHas('stock_balances', [
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => 0,
        ]);
        $this->assertNull($variant->current_unit_cost_10000);
        $this->assertNull($variant->current_price_cents);
    }

    public function test_adjustment_records_delta_and_balance_without_changing_cost_or_price(): void
    {
        $variant = ProductVariant::factory()->create();
        DB::table('product_variants')->where('id', $variant->id)->update([
            'current_unit_cost_10000' => 125000,
            'current_price_cents' => 200000,
        ]);
        $service = new StockAdjustmentService;

        $operationId = $service->adjust($variant->id, 3, 'Bonificación comercial del proveedor', 'ajuste-1');
        $service->adjust($variant->id, -1, 'Regalo o muestra gratuita', 'ajuste-2');

        $this->assertDatabaseHas('stock_balances', ['product_variant_id' => $variant->id, 'quantity_on_hand' => 2]);
        $this->assertDatabaseHas('stock_operations', ['id' => $operationId, 'kind' => 'manual_adjustment', 'reason' => 'Bonificación comercial del proveedor']);
        $this->assertDatabaseHas('stock_movements', ['stock_operation_id' => $operationId, 'product_variant_id' => $variant->id, 'quantity_delta' => 3, 'quantity_after' => 3]);
        $this->assertSame(2, DB::table('stock_movements')->where('product_variant_id', $variant->id)->count());
        $this->assertSame(125000, $variant->fresh()->current_unit_cost_10000);
        $this->assertSame(200000, $variant->fresh()->current_price_cents);
    }

    public function test_adjustment_cannot_make_stock_negative_and_rolls_back_operation(): void
    {
        $variant = ProductVariant::factory()->create();
        $service = new StockAdjustmentService;
        $service->adjust($variant->id, 2, 'Carga de prueba', 'ajuste-1');

        try {
            $service->adjust($variant->id, -3, 'Retiro excesivo', 'ajuste-2');
            $this->fail('El ajuste debía rechazar el stock negativo.');
        } catch (DomainException) {
            $this->assertDatabaseMissing('stock_operations', ['idempotency_key' => 'ajuste-2']);
            $this->assertDatabaseHas('stock_balances', ['product_variant_id' => $variant->id, 'quantity_on_hand' => 2]);
            $this->assertSame(1, DB::table('stock_movements')->count());
        }
    }

    public function test_same_confirmation_key_does_not_apply_the_adjustment_twice(): void
    {
        $variant = ProductVariant::factory()->create();
        $service = new StockAdjustmentService;

        $firstId = $service->adjust($variant->id, 4, 'Reposición gratuita', 'ajuste-repetido');
        $secondId = $service->adjust($variant->id, 4, 'Reposición gratuita', 'ajuste-repetido');

        $this->assertSame($firstId, $secondId);
        $this->assertDatabaseHas('stock_balances', ['product_variant_id' => $variant->id, 'quantity_on_hand' => 4]);
        $this->assertSame(1, DB::table('stock_movements')->count());
    }

    public function test_same_confirmation_key_with_other_data_is_rejected(): void
    {
        $variant = ProductVariant::factory()->create();
        $service = new StockAdjustmentService;
        $service->adjust($variant->id, 2, 'Reposición gratuita', 'ajuste-repetido');

        $this->expectException(InvalidArgumentException::class);

        $service->adjust($variant->id, 3, 'Reposición gratuita', 'ajuste-repetido');
    }

    public function test_two_distinct_confirmations_cannot_take_the_same_last_unit(): void
    {
        $variant = ProductVariant::factory()->create();
        $service = new StockAdjustmentService;
        $service->adjust($variant->id, 1, 'Unidad de prueba', 'ajuste-entrada');
        $service->adjust($variant->id, -1, 'Primera salida', 'ajuste-salida-1');

        try {
            $service->adjust($variant->id, -1, 'Segunda salida', 'ajuste-salida-2');
            $this->fail('La segunda salida debía fallar.');
        } catch (DomainException) {
            $this->assertDatabaseHas('stock_balances', ['product_variant_id' => $variant->id, 'quantity_on_hand' => 0]);
            $this->assertSame(2, DB::table('stock_movements')->count());
        }
    }

    public function test_reason_is_required_before_writing_any_stock_event(): void
    {
        $variant = ProductVariant::factory()->create();
        $service = new StockAdjustmentService;

        try {
            $service->adjust($variant->id, 1, '   ', 'ajuste-sin-motivo');
            $this->fail('El motivo vacío debía fallar.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('stock_operations', 0);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertDatabaseHas('stock_balances', ['product_variant_id' => $variant->id, 'quantity_on_hand' => 0]);
        }
    }

    public function test_unknown_variant_does_not_leave_a_partial_operation(): void
    {
        try {
            (new StockAdjustmentService)->adjust(99999, 1, 'Carga de prueba', 'ajuste-desconocido');
            $this->fail('La presentación inexistente debía rechazarse.');
        } catch (DomainException) {
            $this->assertDatabaseCount('stock_operations', 0);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_duplicate_sku_is_rejected_even_when_names_match(): void
    {
        $group = ProductGroup::factory()->create();
        ProductVariant::factory()->for($group, 'group')->create(['sku' => 'EJ-100', 'name' => 'Perfume Floral']);
        ProductVariant::factory()->for($group, 'group')->create(['sku' => 'EJ-101', 'name' => 'Perfume Floral']);

        $this->assertDatabaseCount('product_variants', 2);

        $this->expectException(QueryException::class);

        ProductVariant::factory()->for($group, 'group')->create(['sku' => 'EJ-100', 'name' => 'Otro nombre']);
    }

    public function test_variant_cannot_reference_a_missing_group(): void
    {
        $this->expectException(QueryException::class);

        ProductVariant::factory()->create(['product_group_id' => 99999]);
    }

    public function test_stock_at_minimum_is_included_in_alert_query(): void
    {
        $variant = ProductVariant::factory()->create(['minimum_stock' => 2]);
        (new StockAdjustmentService)->adjust($variant->id, 2, 'Unidades de prueba', 'ajuste-1');

        $alertCount = DB::table('product_variants')
            ->join('stock_balances', 'product_variants.id', '=', 'stock_balances.product_variant_id')
            ->whereColumn('stock_balances.quantity_on_hand', '<=', 'product_variants.minimum_stock')
            ->count();

        $this->assertSame(1, $alertCount);
    }

    public function test_database_rejects_direct_negative_balance_and_movement_rewrite(): void
    {
        $variant = ProductVariant::factory()->create();
        $operationId = (new StockAdjustmentService)->adjust($variant->id, 1, 'Unidad de prueba', 'ajuste-1');

        try {
            DB::table('stock_balances')->where('product_variant_id', $variant->id)->update(['quantity_on_hand' => -1]);
            $this->fail('La base debía impedir un saldo negativo.');
        } catch (QueryException) {
            $this->assertDatabaseHas('stock_balances', ['product_variant_id' => $variant->id, 'quantity_on_hand' => 1]);
        }

        $this->expectException(QueryException::class);

        DB::table('stock_movements')->where('stock_operation_id', $operationId)->update(['quantity_delta' => 9]);
    }

    public function test_database_rejects_movement_with_a_balance_that_does_not_match(): void
    {
        $variant = ProductVariant::factory()->create();
        $operationId = (new StockAdjustmentService)->adjust($variant->id, 1, 'Unidad de prueba', 'ajuste-1');

        $this->expectException(QueryException::class);

        DB::table('stock_movements')->insert([
            'stock_operation_id' => $operationId,
            'line_position' => 1,
            'product_variant_id' => $variant->id,
            'quantity_delta' => 1,
            'quantity_after' => 2,
            'created_at' => now(),
        ]);
    }
}
