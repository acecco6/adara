<?php

namespace Tests\Feature;

use App\Livewire\Products\Catalog;
use App\Livewire\Products\Detail;
use App\Models\Category;
use App\Models\ProductGroup;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
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

    public function test_status_filter_shows_only_matching_presentations_for_mixed_products(): void
    {
        $group = ProductGroup::factory()->create(['name' => 'Crema Facial']);
        ProductVariant::factory()->for($group, 'group')->create(['sku' => 'AD-CREMA-ACTIVA', 'is_active' => true]);
        ProductVariant::factory()->for($group, 'group')->create(['sku' => 'AD-CREMA-ARCHIVADA', 'is_active' => false]);

        Livewire::test(Catalog::class)
            ->set('status', 'active')
            ->assertSee('Crema Facial')
            ->assertSee('AD-CREMA-ACTIVA')
            ->assertDontSee('AD-CREMA-ARCHIVADA')
            ->set('status', 'archived')
            ->assertSee('Crema Facial')
            ->assertSee('AD-CREMA-ARCHIVADA')
            ->assertDontSee('AD-CREMA-ACTIVA');
    }

    public function test_product_pagination_renders_smooth_scroll_behavior_for_the_shared_paginator(): void
    {
        ProductGroup::factory()->count(13)->create()->each(function (ProductGroup $group): void {
            ProductVariant::factory()->for($group, 'group')->create();
        });

        Livewire::test(Catalog::class)
            ->assertSee('aria-label="Paginación"', false)
            ->assertSee('scrollIntoView', false)
            ->assertSee('prefers-reduced-motion', false);
    }

    public function test_category_filter_keeps_catalog_groups_and_summary_based_on_all_products(): void
    {
        $perfumes = Category::factory()->create(['name' => 'Perfumes']);
        $creams = Category::factory()->create(['name' => 'Cremas']);
        $perfume = ProductGroup::factory()->create(['name' => 'Fragancia', 'category_id' => $perfumes->id]);
        $cream = ProductGroup::factory()->create(['name' => 'Hidratante', 'category_id' => $creams->id]);
        ProductVariant::factory()->for($perfume, 'group')->create();
        ProductVariant::factory()->for($cream, 'group')->create();

        Livewire::test(Catalog::class)
            ->set('selectedCategory', (string) $perfumes->id)
            ->assertSee('Fragancia')
            ->assertDontSee('Hidratante')
            ->assertSee('Mostrando 1 de 1 productos')
            ->assertSee('Presentaciones activas');
    }

    public function test_catalog_shows_each_presentation_with_its_own_price(): void
    {
        $group = ProductGroup::factory()->create(['name' => 'SportDesk']);
        $small = ProductVariant::factory()->for($group, 'group')->create([
            'name' => '50 ml',
            'sku' => 'AD-SPORT-50',
            'current_price_cents' => 900000,
        ]);
        $large = ProductVariant::factory()->for($group, 'group')->create([
            'name' => '100 ml',
            'sku' => 'AD-SPORT-100',
            'current_price_cents' => 1500000,
        ]);

        Livewire::test(Catalog::class)
            ->assertSeeHtml('wire:key="product-variant-'.$small->id.'"')
            ->assertSeeHtml('wire:key="product-variant-'.$large->id.'"')
            ->assertSeeInOrder(['50 ml', 'AD-SPORT-50', '$ 9.000,00', '100 ml', 'AD-SPORT-100', '$ 15.000,00'])
            ->assertSee('1 de 1 productos · 2 presentaciones en esta página')
            ->assertDontSee('Precio desde');
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

    public function test_detail_refuses_to_delete_variant_linked_to_a_draft_purchase_order(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = PurchaseOrder::factory()->create();
        PurchaseOrderLine::factory()->for($order, 'order')->for($variant, 'variant')->create();

        Livewire::test(Detail::class, ['groupId' => $variant->product_group_id])
            ->call('confirm', 'delete_variant', $variant->id)
            ->call('performConfirmed')
            ->assertHasErrors('catalog')
            ->assertSee('está vinculada a operaciones y no se puede eliminar');

        $this->assertModelExists($variant);
        $this->assertDatabaseHas('purchase_order_lines', [
            'purchase_order_id' => $order->id,
            'product_variant_id' => $variant->id,
        ]);
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
