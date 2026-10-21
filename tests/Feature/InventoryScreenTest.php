<?php

namespace Tests\Feature;

use App\Livewire\InventoryIndex;
use App\Models\ProductGroup;
use App\Models\ProductVariant;
use App\Services\InitialStockService;
use App\Services\StockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_page_has_honest_empty_states(): void
    {
        $this->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('No hay presentaciones para mostrar')
            ->assertSee('Todavía no hay movimientos para mostrar.')
            ->assertDontSee('En preparación');
    }

    public function test_filters_stock_by_name_state_and_threshold_including_equality(): void
    {
        $floral = ProductGroup::factory()->create(['name' => 'Perfume Floral']);
        $facial = ProductGroup::factory()->create(['name' => 'Crema Facial']);
        $low = ProductVariant::factory()->for($floral, 'group')->create(['sku' => 'AD-FLORAL', 'minimum_stock' => 5]);
        $available = ProductVariant::factory()->for($facial, 'group')->create(['sku' => 'AD-FACIAL', 'minimum_stock' => 2]);
        $archived = ProductVariant::factory()->for($facial, 'group')->create(['sku' => 'AD-ARCHIVED', 'is_active' => false]);
        app(InitialStockService::class)->record($low->id, '5', '100', 'stock-low');
        app(InitialStockService::class)->record($available->id, '8', '100', 'stock-available');

        Livewire::test(InventoryIndex::class)
            ->assertSee('inventory-variant-'.$low->id)
            ->assertSee('inventory-variant-'.$available->id)
            ->assertDontSee('inventory-variant-'.$archived->id)
            ->set('level', 'at_minimum')
            ->assertSee('inventory-variant-'.$low->id)
            ->assertDontSee('inventory-variant-'.$available->id)
            ->set('search', 'FACIAL')
            ->assertDontSee('inventory-variant-'.$low->id)
            ->assertSee('No hay presentaciones para mostrar')
            ->set('level', 'available')
            ->assertSee('inventory-variant-'.$available->id)
            ->set('status', 'archived')
            ->set('level', 'out')
            ->assertSee('inventory-variant-'.$archived->id)
            ->assertDontSee('inventory-variant-'.$available->id);
    }

    public function test_history_filters_by_presentation_and_keeps_audited_initial_cost(): void
    {
        $first = ProductVariant::factory()->create();
        $second = ProductVariant::factory()->create();
        app(InitialStockService::class)->record($first->id, '3', '123,4567', 'history-first');
        app(StockAdjustmentService::class)->adjust($second->id, 1, 'Reemplazo gratuito', 'history-second');

        Livewire::test(InventoryIndex::class)
            ->assertSee('Reemplazo gratuito')
            ->call('showHistory', $first->id)
            ->assertSee('Costo unitario: $ 123,4567')
            ->assertDontSee('Reemplazo gratuito')
            ->call('clearHistory')
            ->assertSee('Reemplazo gratuito');
    }

    public function test_modal_adjustment_changes_stock_exactly_once_and_keeps_cost(): void
    {
        $variant = ProductVariant::factory()->create();
        app(InitialStockService::class)->record($variant->id, '3', '80,0000', 'initial-modal');

        Livewire::test(InventoryIndex::class)
            ->call('openAdjustment', $variant->id)
            ->set('adjustmentDirection', 'out')
            ->set('adjustmentQuantity', '2')
            ->set('adjustmentReason', 'Regalo o muestra gratuita')
            ->call('saveAdjustment')
            ->assertHasNoErrors()
            ->call('saveAdjustment')
            ->assertHasNoErrors()
            ->assertSee('Regalo o muestra gratuita');

        $this->assertSame(1, $variant->fresh()->balance->quantity_on_hand);
        $this->assertSame(800000, $variant->fresh()->current_unit_cost_10000);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertSame(1, DB::table('stock_operations')->where('kind', 'manual_adjustment')->count());
    }

    public function test_modal_blocks_negative_stock_and_missing_reason(): void
    {
        $variant = ProductVariant::factory()->create();

        Livewire::test(InventoryIndex::class)
            ->call('openAdjustment', $variant->id)
            ->set('adjustmentDirection', 'out')
            ->set('adjustmentQuantity', '1')
            ->set('adjustmentReason', 'Faltante')
            ->call('saveAdjustment')
            ->assertHasErrors('adjustment')
            ->set('adjustmentDirection', 'in')
            ->set('adjustmentReason', '')
            ->call('saveAdjustment')
            ->assertHasErrors('adjustment');

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(0, $variant->fresh()->balance->quantity_on_hand);
    }

    public function test_archived_variant_cannot_be_adjusted(): void
    {
        $variant = ProductVariant::factory()->create(['is_active' => false]);

        Livewire::test(InventoryIndex::class)
            ->call('openAdjustment', $variant->id)
            ->assertHasErrors('adjustment');

        $this->assertDatabaseCount('stock_movements', 0);
    }
}
