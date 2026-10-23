<?php

namespace Tests\Feature;

use App\Livewire\PublicCatalog;
use App\Livewire\PublicCatalogProductDetail;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\Category;
use App\Models\ProductGroup;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected bool $authenticateByDefault = false;

    public function test_catalog_and_images_are_unavailable_until_enabled(): void
    {
        $group = ProductGroup::factory()->create();
        ProductVariant::factory()->for($group, 'group')->create();
        $image = ProductImage::factory()->for($group, 'group')->create();

        $this->get(route('catalog.public'))->assertNotFound();
        $this->get(route('catalog.images.show', $image))->assertNotFound();
        $this->assertDatabaseHas('business_settings', ['id' => 1, 'public_catalog_enabled' => 0]);
    }

    public function test_settings_toggle_controls_guest_access_without_exposing_archived_images(): void
    {
        $visibleGroup = ProductGroup::factory()->create(['name' => 'Crema visible']);
        $visibleVariant = ProductVariant::factory()->for($visibleGroup, 'group')->create(['current_price_cents' => 1450000]);
        $visibleImage = ProductImage::factory()->for($visibleGroup, 'group')->create();
        $archivedGroup = ProductGroup::factory()->create(['name' => 'Crema archivada']);
        $archivedVariant = ProductVariant::factory()->for($archivedGroup, 'group')->create(['is_active' => false]);
        $archivedImage = ProductImage::factory()->for($archivedGroup, 'group')->create();

        $this->actingAs(User::factory()->create());
        Livewire::test(SettingsIndex::class)->call('togglePublicCatalog')->assertSet('publicCatalogEnabled', true);
        $this->assertDatabaseHas('business_settings', ['id' => 1, 'public_catalog_enabled' => 1]);
        auth()->logout();

        $this->get(route('catalog.public'))->assertOk()->assertSee('Crema visible')->assertDontSee('Crema archivada')
            ->assertSee("wire:click=\"\$dispatchTo('public-catalog-product-detail', 'public-product-detail-load', { variantId: {$visibleVariant->id} })\"", false)
            ->assertSee('public-product-detail-reset', false)
            ->assertDontSee('$wire.resetProduct()', false);
        $this->get(route('catalog.product.show', $visibleVariant))->assertOk()->assertSee('Crema visible')->assertSee('$ 14.500');
        $this->get(route('catalog.product.show', $archivedVariant))->assertNotFound();
        $this->get(route('catalog.images.show', $visibleImage))->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'max-age=300, private');
        $this->get(route('catalog.images.show', $archivedImage))->assertNotFound();

        $this->actingAs(User::factory()->create());
        Livewire::test(SettingsIndex::class)->call('togglePublicCatalog')->assertSet('publicCatalogEnabled', false);
        auth()->logout();

        $this->get(route('catalog.public'))->assertNotFound();
        $this->get(route('catalog.product.show', $visibleVariant))->assertNotFound();
        $this->get(route('catalog.images.show', $visibleImage))->assertNotFound();
    }

    public function test_guest_can_filter_by_name_category_and_subcategory_and_sort_prices_both_ways(): void
    {
        DB::table('business_settings')->where('id', 1)->update(['public_catalog_enabled' => true]);
        $face = Category::factory()->create(['name' => 'Rostro']);
        $body = Category::factory()->create(['name' => 'Cuerpo']);
        $serums = Subcategory::factory()->for($face)->create(['name' => 'Sérums']);
        $creams = Subcategory::factory()->for($face)->create(['name' => 'Cremas']);
        $cheap = ProductGroup::factory()->create(['name' => 'Sérum económico', 'category_id' => $face->id, 'subcategory_id' => $serums->id]);
        $expensive = ProductGroup::factory()->create(['name' => 'Sérum premium', 'category_id' => $face->id, 'subcategory_id' => $serums->id]);
        $otherFace = ProductGroup::factory()->create(['name' => 'Crema facial', 'category_id' => $face->id, 'subcategory_id' => $creams->id]);
        $otherBody = ProductGroup::factory()->create(['name' => 'Aceite corporal', 'category_id' => $body->id]);
        ProductVariant::factory()->for($cheap, 'group')->create(['current_price_cents' => 1000000]);
        ProductVariant::factory()->for($expensive, 'group')->create(['current_price_cents' => 3000000]);
        ProductVariant::factory()->for($otherFace, 'group')->create(['current_price_cents' => 2000000]);
        ProductVariant::factory()->for($otherBody, 'group')->create(['current_price_cents' => 500000]);

        $catalog = Livewire::test(PublicCatalog::class)
            ->set('search', 'Sérum')
            ->assertSee('Sérum económico')->assertSee('Sérum premium')->assertDontSee('Crema facial')
            ->set('selectedCategory', (string) $face->id)
            ->set('selectedSubcategory', (string) $serums->id)
            ->assertSee('Sérum económico')->assertDontSee('Aceite corporal');

        $catalog->set('sort', 'price_asc');
        $this->assertSame(['Sérum económico', 'Sérum premium'], $catalog->viewData('products')->getCollection()->map(fn ($product) => $product->group->name)->all());

        $catalog->set('sort', 'price_desc');
        $this->assertSame(['Sérum premium', 'Sérum económico'], $catalog->viewData('products')->getCollection()->map(fn ($product) => $product->group->name)->all());

        $catalog->call('clearFilters')->assertSee('Aceite corporal')->assertSee('Crema facial');
    }

    public function test_guest_opens_product_detail_in_catalog_modal_without_losing_filters(): void
    {
        DB::table('business_settings')->where('id', 1)->update(['public_catalog_enabled' => true]);
        $category = Category::factory()->create(['name' => 'Rostro']);
        $group = ProductGroup::factory()->create([
            'name' => 'Crema de noche',
            'description' => 'Humecta y suaviza la piel.',
            'category_id' => $category->id,
        ]);
        $variant = ProductVariant::factory()->for($group, 'group')->create([
            'name' => 'Frasco 50 ml',
            'current_price_cents' => 2250000,
        ]);
        $archivedGroup = ProductGroup::factory()->create(['name' => 'Producto archivado']);
        $archivedVariant = ProductVariant::factory()->for($archivedGroup, 'group')->create(['is_active' => false]);

        Livewire::test(PublicCatalog::class)
            ->set('selectedCategory', (string) $category->id)
            ->assertSee('Crema de noche')
            ->assertSee("\$dispatchTo('public-catalog-product-detail', 'public-product-detail-load', { variantId: {$variant->id} })", false);

        Livewire::test(PublicCatalogProductDetail::class)
            ->dispatch('public-product-detail-load', variantId: $variant->id)
            ->assertDispatched('adara:open', id: 'public-product-detail')
            ->assertSet('variantId', $variant->id)
            ->assertSee('Crema de noche')
            ->assertSee('Frasco 50 ml')
            ->assertSee('$ 22.500')
            ->assertSee('Humecta y suaviza la piel.')
            ->dispatch('public-product-detail-reset')
            ->assertSet('variantId', null)
            ->assertDontSee('Crema de noche');

        Livewire::test(PublicCatalogProductDetail::class)
            ->dispatch('public-product-detail-load', variantId: $archivedVariant->id)
            ->assertSee('No pudimos cargar este producto')
            ->assertDontSee('Producto archivado');
    }

    public function test_guest_can_update_public_catalog_over_livewire_http_route(): void
    {
        DB::table('business_settings')->where('id', 1)->update(['public_catalog_enabled' => true]);
        $category = Category::factory()->create(['name' => 'Rostro']);
        $group = ProductGroup::factory()->create(['name' => 'Crema pública', 'category_id' => $category->id]);
        ProductVariant::factory()->for($group, 'group')->create();

        $response = $this->get(route('catalog.public'))->assertOk();
        $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches));
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES);

        $update = $this->postJson(route('livewire.update'), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => ['selectedCategory' => (string) $category->id],
                'calls' => [],
            ]],
        ], ['X-Livewire' => 'true'])->assertOk()->assertJsonCount(1, 'components');
        $this->assertStringContainsString('Crema pública', $update->json('components.0.effects.html'));

        DB::table('business_settings')->where('id', 1)->update(['public_catalog_enabled' => false]);

        $this->postJson(route('livewire.update'), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => ['selectedCategory' => (string) $category->id],
                'calls' => [],
            ]],
        ], ['X-Livewire' => 'true'])->assertNotFound();
    }

    public function test_private_livewire_update_still_requires_authentication(): void
    {
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('settings.index'))->assertOk();
        $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches));
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES);
        auth()->logout();

        $this->postJson(route('livewire.update'), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [],
            ]],
        ], ['X-Livewire' => 'true'])->assertUnauthorized();
    }

    public function test_public_catalog_keeps_empty_state_and_does_not_show_inactive_presentations(): void
    {
        DB::table('business_settings')->where('id', 1)->update(['public_catalog_enabled' => true]);
        $group = ProductGroup::factory()->create(['name' => 'Producto oculto']);
        ProductVariant::factory()->for($group, 'group')->create(['is_active' => false]);

        $this->get(route('catalog.public'))->assertOk()->assertSee('No encontramos productos')->assertDontSee('Producto oculto');
    }
}
