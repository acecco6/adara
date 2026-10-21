<?php

namespace Tests\Feature;

use App\Models\ProductGroup;
use App\Models\ProductVariant;
use App\Services\CatalogService;
use App\Services\StockAdjustmentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_group_and_first_sku_with_zero_balance_and_no_invented_price(): void
    {
        $group = app(CatalogService::class)->createProduct([
            'name' => '  Perfume floral  ',
            'presentation' => ' 50 ml ',
            'sku' => '  ad-floral-50 ',
        ]);
        $variant = $group->variants()->firstOrFail();

        $this->assertSame('Perfume floral', $group->name);
        $this->assertSame('50 ml', $variant->name);
        $this->assertSame('AD-FLORAL-50', $variant->sku);
        $this->assertSame(0, $variant->balance->quantity_on_hand);
        $this->assertNull($variant->current_unit_cost_10000);
        $this->assertNull($variant->current_price_cents);
    }

    public function test_generates_unique_sku_when_manual_code_is_blank_and_allows_duplicate_names(): void
    {
        $service = app(CatalogService::class);

        $first = $service->createProduct(['name' => 'Perfume', 'presentation' => '50 ml', 'sku' => '']);
        $second = $service->createProduct(['name' => 'Perfume', 'presentation' => '50 ml', 'sku' => '']);

        $this->assertNotSame($first->variants()->firstOrFail()->sku, $second->variants()->firstOrFail()->sku);
        $this->assertSame('Perfume', $second->name);
    }

    public function test_duplicate_manual_sku_rejects_second_product_and_rolls_back_group(): void
    {
        $service = app(CatalogService::class);
        $service->createProduct(['name' => 'Primero', 'presentation' => '50 ml', 'sku' => 'AD-1']);

        try {
            $service->createProduct(['name' => 'Segundo', 'presentation' => '100 ml', 'sku' => 'ad-1']);
            $this->fail('El SKU repetido debía rechazarse.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('product_groups', 1);
            $this->assertDatabaseCount('product_variants', 1);
        }
    }

    public function test_variant_edit_preserves_cost_price_and_stock_history(): void
    {
        $variant = ProductVariant::factory()->create();
        DB::table('product_variants')->where('id', $variant->id)->update([
            'current_unit_cost_10000' => 125000,
            'current_price_cents' => 200000,
        ]);
        app(StockAdjustmentService::class)->adjust($variant->id, 2, 'Ingreso de prueba', 'catalogo-ajuste-1');

        $updated = app(CatalogService::class)->updateVariant($variant->id, ['presentation' => '100 ml', 'sku' => 'ad-nuevo']);

        $this->assertSame('100 ml', $updated->name);
        $this->assertSame('AD-NUEVO', $updated->sku);
        $this->assertSame(125000, $updated->current_unit_cost_10000);
        $this->assertSame(200000, $updated->current_price_cents);
        $this->assertSame(2, $updated->balance->quantity_on_hand);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_archiving_requires_zero_stock_and_reactivation_preserves_identity(): void
    {
        $variant = ProductVariant::factory()->create();
        app(StockAdjustmentService::class)->adjust($variant->id, 1, 'Ingreso de prueba', 'catalogo-ajuste-2');
        $service = app(CatalogService::class);

        try {
            $service->setActive($variant->id, false);
            $this->fail('No debe archivarse con stock.');
        } catch (DomainException) {
            $this->assertTrue($variant->fresh()->is_active);
        }

        app(StockAdjustmentService::class)->adjust($variant->id, -1, 'Salida de prueba', 'catalogo-ajuste-3');
        $service->setActive($variant->id, false);
        $this->assertFalse($variant->fresh()->is_active);

        $service->setActive($variant->id, true);
        $this->assertTrue($variant->fresh()->is_active);
        $this->assertSame(0, $variant->balance()->firstOrFail()->quantity_on_hand);
        $this->assertSame(2, DB::table('stock_movements')->where('product_variant_id', $variant->id)->count());
    }

    public function test_only_unused_zero_stock_variant_can_be_deleted(): void
    {
        $unused = ProductVariant::factory()->create();
        $used = ProductVariant::factory()->create();
        $service = app(CatalogService::class);
        app(StockAdjustmentService::class)->adjust($used->id, 1, 'Ingreso de prueba', 'catalogo-ajuste-4');
        app(StockAdjustmentService::class)->adjust($used->id, -1, 'Salida de prueba', 'catalogo-ajuste-5');

        $service->deleteUnusedVariant($unused->id);

        $this->assertModelMissing($unused);
        $this->assertDatabaseMissing('stock_balances', ['product_variant_id' => $unused->id]);

        try {
            $service->deleteUnusedVariant($used->id);
            $this->fail('El historial debía impedir el borrado.');
        } catch (DomainException) {
            $this->assertModelExists($used);
            $this->assertDatabaseCount('stock_movements', 2);
        }
    }

    public function test_group_cannot_be_deleted_while_it_contains_a_variant(): void
    {
        $group = ProductGroup::factory()->create();
        ProductVariant::factory()->for($group, 'group')->create();

        $this->expectException(DomainException::class);

        app(CatalogService::class)->deleteEmptyGroup($group->id);
    }
}
