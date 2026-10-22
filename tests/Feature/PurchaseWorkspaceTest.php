<?php

namespace Tests\Feature;

use App\Livewire\PurchaseWorkspace;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_pages_render_empty_states_and_unknown_detail_returns_404(): void
    {
        $this->get(route('purchases.index'))->assertOk()->assertSee('Todavía no hay pedidos');
        $this->get(route('purchases.receipts'))->assertOk()->assertSee('Todavía no hay recepciones');
        $this->get(route('purchases.show', 999))->assertNotFound();
    }

    public function test_order_status_filter_keeps_only_matching_orders(): void
    {
        $supplier = Supplier::factory()->create();
        $variant = ProductVariant::factory()->create();
        $service = app(PurchaseOrderService::class);
        $input = [
            'supplier_id' => $supplier->id,
            'shipping_method' => 'quantity',
            'general_shipping' => '0',
            'lines' => [['variant_id' => $variant->id, 'quantity' => '1', 'unit_paid' => '100']],
        ];
        $draft = $service->createDraft($input);
        $confirmed = $service->confirm($service->createDraft($input)->id);

        Livewire::test(PurchaseWorkspace::class, ['view' => 'index'])
            ->set('status', 'draft')
            ->assertSee($draft->number)
            ->assertDontSee($confirmed->number)
            ->set('status', 'confirmed')
            ->assertSee($confirmed->number)
            ->assertDontSee($draft->number)
            ->set('status', 'received')
            ->assertSee('No hay pedidos con estos filtros');
    }

    public function test_livewire_creates_draft_and_can_confirm_it_from_detail(): void
    {
        $supplier = Supplier::factory()->create();
        $variant = ProductVariant::factory()->create();

        Livewire::test(PurchaseWorkspace::class, ['view' => 'index'])
            ->call('openCreate')
            ->set('supplierId', (string) $supplier->id)
            ->set('shippingMethod', 'quantity')
            ->set('generalShipping', '10,00')
            ->set('orderLines', [[
                '_key' => 'first',
                'variant_id' => (string) $variant->id,
                'quantity' => '2',
                'unit_paid' => '100,00',
            ]])
            ->call('saveDraft')
            ->assertHasNoErrors();

        $order = PurchaseOrder::query()->firstOrFail();
        $this->assertSame('draft', $order->status);
        $this->assertSame(0, $variant->fresh()->balance->quantity_on_hand);

        Livewire::test(PurchaseWorkspace::class, ['view' => 'detail', 'orderId' => $order->id])
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSee('Confirmado');

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->get(route('purchases.show', $order))->assertOk()->assertSee($order->number);
    }

    public function test_livewire_receives_partially_then_closes_pending_without_repricing(): void
    {
        $supplier = Supplier::factory()->create();
        $variant = ProductVariant::factory()->create();
        $service = app(PurchaseOrderService::class);
        $order = $service->confirm($service->createDraft([
            'supplier_id' => $supplier->id,
            'shipping_method' => 'quantity',
            'general_shipping' => '10',
            'lines' => [['variant_id' => $variant->id, 'quantity' => '2', 'unit_paid' => '100']],
        ])->id);

        Livewire::test(PurchaseWorkspace::class, ['view' => 'detail', 'orderId' => $order->id])
            ->call('openReceipt')
            ->set('receiptLines.0.quantity', '1')
            ->call('saveReceipt')
            ->assertHasNoErrors()
            ->assertSee('Recibido parcialmente')
            ->set('closureReason', 'No llega la unidad restante')
            ->call('closePending')
            ->assertHasNoErrors()
            ->assertSee('Cerrado parcialmente');

        $this->assertSame(1, $variant->fresh()->balance->quantity_on_hand);
        $this->assertSame(1050000, $variant->fresh()->current_unit_cost_10000);
        $this->assertDatabaseHas('purchase_order_closures', [
            'purchase_order_id' => $order->id,
            'unassigned_general_shipping_cents' => 500,
        ]);
        $this->get(route('purchases.receipts'))
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee($variant->group->name)
            ->assertSee('105,0000')
            ->assertSee('Mostrando 1 de 1 renglones recibidos');
    }
}
