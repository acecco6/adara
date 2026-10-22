<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\InitialStockService;
use App\Services\PurchaseOrderService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_has_number_and_ordered_lines_without_changing_inventory(): void
    {
        $supplier = Supplier::factory()->create();
        $first = ProductVariant::factory()->create();
        $second = ProductVariant::factory()->create();

        $order = app(PurchaseOrderService::class)->createDraft([
            'supplier_id' => $supplier->id,
            'shipping_method' => 'merchandise_cost',
            'general_shipping' => '123,45',
            'lines' => [
                ['variant_id' => $first->id, 'quantity' => '2', 'unit_paid' => '1000,00'],
                ['variant_id' => $second->id, 'quantity' => '1', 'unit_paid' => '500,50'],
            ],
        ]);

        $this->assertSame('PED-000001', $order->number);
        $this->assertSame('draft', $order->status);
        $this->assertSame(12345, $order->general_shipping_cents);
        $this->assertSame([0, 1], $order->lines->pluck('line_position')->all());
        $this->assertSame([100000, 50050], $order->lines->pluck('unit_paid_cents')->all());
        $this->assertNull($order->confirmed_at);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(0, $first->fresh()->balance->quantity_on_hand);
        $this->assertNull($first->fresh()->current_unit_cost_10000);
        $this->assertNull($first->fresh()->current_price_cents);
    }

    public function test_edit_draft_and_confirm_once_freezes_catalog_snapshots(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Proveedor Original']);
        $variant = ProductVariant::factory()->create(['sku' => 'AD-ORIGINAL', 'name' => '50 ml']);
        $service = app(PurchaseOrderService::class);
        $order = $service->createDraft($this->input($supplier, $variant, '2'));

        $service->updateDraft($order->id, $this->input($supplier, $variant, '3'));
        $confirmed = $service->confirm($order->id);
        $confirmedAgain = $service->confirm($order->id);

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertSame($confirmed->confirmed_at->toIso8601String(), $confirmedAgain->confirmed_at->toIso8601String());
        $this->assertSame('Proveedor Original', $confirmed->supplier_name_snapshot);
        $this->assertSame('AD-ORIGINAL', $confirmed->lines->firstOrFail()->sku_snapshot);
        $this->assertSame('50 ml', $confirmed->lines->firstOrFail()->presentation_snapshot);
        $this->assertSame(3, $confirmed->lines->firstOrFail()->quantity_ordered);
        $this->assertDatabaseCount('purchase_order_lines', 1);
        $this->assertDatabaseCount('stock_movements', 0);

        $supplier->update(['name' => 'Proveedor Nuevo']);
        $variant->update(['sku' => 'AD-NUEVO']);
        $this->assertSame('Proveedor Original', $confirmed->fresh()->supplier_name_snapshot);
        $this->assertSame('AD-ORIGINAL', $confirmed->fresh()->lines->firstOrFail()->sku_snapshot);
    }

    public function test_confirmed_order_cannot_be_edited(): void
    {
        $supplier = Supplier::factory()->create();
        $variant = ProductVariant::factory()->create();
        $service = app(PurchaseOrderService::class);
        $order = $service->createDraft($this->input($supplier, $variant, '2'));
        $service->confirm($order->id);

        $this->expectException(DomainException::class);
        $service->updateDraft($order->id, $this->input($supplier, $variant, '4'));
    }

    public function test_empty_draft_cannot_be_confirmed(): void
    {
        $supplier = Supplier::factory()->create();
        $service = app(PurchaseOrderService::class);
        $order = $service->createDraft(['supplier_id' => $supplier->id, 'shipping_method' => 'quantity', 'lines' => []]);

        try {
            $service->confirm($order->id);
            $this->fail('El pedido vacío debía permanecer en borrador.');
        } catch (DomainException) {
            $this->assertSame('draft', $order->fresh()->status);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_inactive_supplier_or_variant_blocks_confirmation(): void
    {
        $supplier = Supplier::factory()->create();
        $variant = ProductVariant::factory()->create();
        $service = app(PurchaseOrderService::class);
        $order = $service->createDraft($this->input($supplier, $variant, '1'));
        $supplier->update(['is_active' => false]);

        try {
            $service->confirm($order->id);
            $this->fail('El proveedor archivado debía impedir la confirmación.');
        } catch (DomainException) {
            $this->assertSame('draft', $order->fresh()->status);
        }

        $supplier->update(['is_active' => true]);
        $variant->update(['is_active' => false]);
        $this->expectException(DomainException::class);
        $service->confirm($order->id);
    }

    public function test_invalid_money_or_quantity_does_not_create_order(): void
    {
        $supplier = Supplier::factory()->create();
        $variant = ProductVariant::factory()->create();
        $service = app(PurchaseOrderService::class);

        foreach ([
            ['general_shipping' => '-1'],
            ['shipping_method' => 'otro'],
            ['lines' => [['variant_id' => $variant->id, 'quantity' => '0', 'unit_paid' => '1']]],
            ['lines' => [['variant_id' => $variant->id, 'quantity' => '1', 'unit_paid' => '1,234']]],
        ] as $changes) {
            try {
                $service->createDraft(array_replace($this->input($supplier, $variant, '2'), $changes));
                $this->fail('El pedido inválido debía rechazarse.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('purchase_orders', 0);
            }
        }
    }

    public function test_zero_paid_line_can_be_draft_but_cannot_be_confirmed_as_purchase(): void
    {
        $supplier = Supplier::factory()->create();
        $variant = ProductVariant::factory()->create();
        $service = app(PurchaseOrderService::class);
        $input = $this->input($supplier, $variant, '1');
        $input['lines'][0]['unit_paid'] = '0';
        $order = $service->createDraft($input);

        $this->expectException(DomainException::class);
        $service->confirm($order->id);
    }

    public function test_confirmed_order_and_lines_cannot_be_rewritten_directly(): void
    {
        $supplier = Supplier::factory()->create();
        $variant = ProductVariant::factory()->create();
        $service = app(PurchaseOrderService::class);
        $order = $service->confirm($service->createDraft($this->input($supplier, $variant, '2'))->id);
        $lineId = $order->lines->firstOrFail()->id;

        foreach ([
            fn () => DB::table('purchase_orders')->where('id', $order->id)->update(['general_shipping_cents' => 0]),
            fn () => DB::table('purchase_order_lines')->where('id', $lineId)->update(['quantity_ordered' => 9]),
            fn () => DB::table('purchase_order_lines')->where('id', $lineId)->delete(),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('El historial confirmado no debe reescribirse.');
            } catch (QueryException) {
                $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'general_shipping_cents' => 1000]);
                $this->assertDatabaseHas('purchase_order_lines', ['id' => $lineId, 'quantity_ordered' => 2]);
            }
        }
    }

    public function test_order_number_is_unique_and_catalog_references_are_restricted(): void
    {
        $supplier = Supplier::factory()->create();
        $variant = ProductVariant::factory()->create();
        $service = app(PurchaseOrderService::class);
        $first = $service->createDraft($this->input($supplier, $variant, '1'));
        $second = $service->createDraft($this->input($supplier, $variant, '1'));

        $this->assertNotSame($first->number, $second->number);
        $this->assertDatabaseHas('purchase_order_lines', ['purchase_order_id' => $first->id, 'product_variant_id' => $variant->id]);

        $this->expectException(QueryException::class);
        $supplier->delete();
    }

    public function test_confirming_order_does_not_change_existing_stock_cost_or_price(): void
    {
        $supplier = Supplier::factory()->create();
        $variant = ProductVariant::factory()->create();
        app(InitialStockService::class)->record($variant->id, '4', '120.0000', 'purchase-existing-stock');
        DB::table('product_variants')->where('id', $variant->id)->update(['current_price_cents' => 20000]);
        $service = app(PurchaseOrderService::class);

        $order = $service->createDraft($this->input($supplier, $variant, '6'));
        $service->confirm($order->id);

        $this->assertSame(4, $variant->fresh()->balance->quantity_on_hand);
        $this->assertSame(1200000, $variant->fresh()->current_unit_cost_10000);
        $this->assertSame(20000, $variant->fresh()->current_price_cents);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    /** @return array<string, mixed> */
    private function input(Supplier $supplier, ProductVariant $variant, string $quantity): array
    {
        return [
            'supplier_id' => $supplier->id,
            'shipping_method' => 'quantity',
            'general_shipping' => '10.00',
            'lines' => [['variant_id' => $variant->id, 'quantity' => $quantity, 'unit_paid' => '100.00']],
        ];

    }
}
