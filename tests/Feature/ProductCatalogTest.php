<?php

namespace Tests\Feature;

use App\Livewire\Products\Catalog;
use App\Livewire\Products\Detail;
use App\Models\ProductGroup;
use App\Models\ProductVariant;
use App\Services\StockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_product_and_open_detail(): void
    {
        Livewire::test(Catalog::class)
            ->set('name', 'Perfume floral')
            ->set('presentation', '50 ml')
            ->set('sku', ' ad-floral-50 ')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('products.show', 1));

        $this->assertDatabaseHas('product_groups', ['id' => 1, 'name' => 'Perfume floral']);
        $this->assertDatabaseHas('product_variants', ['product_group_id' => 1, 'sku' => 'AD-FLORAL-50']);
        $this->get(route('products.show', 1))->assertOk()->assertSee('AD-FLORAL-50');
    }

    public function test_invalid_product_is_not_saved(): void
    {
        Livewire::test(Catalog::class)
            ->set('name', '   ')
            ->set('presentation', '50 ml')
            ->call('save')
            ->assertHasErrors('name');

        $this->assertDatabaseCount('product_groups', 0);
    }

    public function test_search_and_status_filter_show_matching_products(): void
    {
        $active = ProductGroup::factory()->create(['name' => 'Perfume Floral']);
        ProductVariant::factory()->for($active, 'group')->create(['sku' => 'AD-FLORAL', 'is_active' => true]);
        $archived = ProductGroup::factory()->create(['name' => 'Crema Facial']);
        ProductVariant::factory()->for($archived, 'group')->create(['sku' => 'AD-CREMA', 'is_active' => false]);

        Livewire::test(Catalog::class)
            ->set('search', 'floral')
            ->assertSee('Perfume Floral')
            ->assertDontSee('Crema Facial')
            ->set('search', '')
            ->set('status', 'archived')
            ->assertSee('Crema Facial')
            ->assertDontSee('Perfume Floral');
    }

    public function test_detail_adds_and_edits_presentations_without_changing_prices(): void
    {
        $group = ProductGroup::factory()->create();

        Livewire::test(Detail::class, ['groupId' => $group->id])
            ->set('presentation', '100 ml')
            ->set('sku', 'AD-100')
            ->call('addVariant')
            ->assertHasNoErrors()
            ->assertSee('AD-100');

        $variant = $group->variants()->firstOrFail();

        Livewire::test(Detail::class, ['groupId' => $group->id])
            ->call('startEditing', $variant->id)
            ->set('editPresentation', '200 ml')
            ->set('editSku', 'AD-200')
            ->call('saveVariant')
            ->assertHasNoErrors()
            ->assertSee('AD-200');

        $this->assertSame('200 ml', $variant->fresh()->name);
        $this->assertNull($variant->fresh()->current_price_cents);
    }

    public function test_detail_keeps_stock_history_and_refuses_archive_with_stock(): void
    {
        $variant = ProductVariant::factory()->create();
        app(StockAdjustmentService::class)->adjust($variant->id, 1, 'Ingreso de prueba', 'catalogo-ui-1');

        Livewire::test(Detail::class, ['groupId' => $variant->product_group_id])
            ->assertSee('Ingreso de prueba')
            ->call('confirm', 'archive', $variant->id)
            ->call('performConfirmed')
            ->assertHasErrors('catalog')
            ->assertSee('Ingreso de prueba');

        $this->assertTrue($variant->fresh()->is_active);
    }

    public function test_detail_does_not_allow_actions_on_a_variant_from_another_product(): void
    {
        $group = ProductGroup::factory()->create();
        $otherVariant = ProductVariant::factory()->create();

        Livewire::test(Detail::class, ['groupId' => $group->id])
            ->call('confirm', 'archive', $otherVariant->id)
            ->assertNotFound();

        $this->assertTrue($otherVariant->fresh()->is_active);
    }

    public function test_detail_confirms_archive_and_can_reactivate_same_sku(): void
    {
        $variant = ProductVariant::factory()->create();

        Livewire::test(Detail::class, ['groupId' => $variant->product_group_id])
            ->call('confirm', 'archive', $variant->id)
            ->call('performConfirmed')
            ->assertHasNoErrors()
            ->assertSee('Archivado')
            ->call('reactivate', $variant->id)
            ->assertSee('Activo');

        $this->assertTrue($variant->fresh()->is_active);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_edit_requires_sku_and_does_not_generate_a_replacement_silently(): void
    {
        $variant = ProductVariant::factory()->create(['sku' => 'AD-ORIGINAL']);

        Livewire::test(Detail::class, ['groupId' => $variant->product_group_id])
            ->call('startEditing', $variant->id)
            ->set('editSku', '')
            ->call('saveVariant')
            ->assertHasErrors('editSku');

        $this->assertSame('AD-ORIGINAL', $variant->fresh()->sku);
    }

    public function test_product_name_is_escaped_in_list_and_detail(): void
    {
        $group = ProductGroup::factory()->create(['name' => '<script>alert(1)</script>']);
        $this->withoutVite();

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>', false);

        $this->get(route('products.show', $group))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>', false);
    }
}
