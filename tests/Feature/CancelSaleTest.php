<?php

namespace Tests\Feature;

use App\Actions\Purchases\ReceivePurchaseOrderAction;
use App\Actions\Sales\CancelSaleAction;
use App\Actions\Sales\CreateSaleAction;
use App\Livewire\SalesHistory;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CancelSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_restores_only_usable_stock_and_does_not_repeat(): void
    {
        $variant = $this->stockedVariant(3);
        $saleId = app(CreateSaleAction::class)->execute([['variant_id' => $variant->id, 'quantity' => '3']], Customer::factory()->create()->id, [], 'none', '0', (string) Str::uuid());
        $itemId = (int) DB::table('sale_items')->where('sale_id', $saleId)->value('id');
        $dispositions = [$itemId => ['usable' => 2, 'reason' => 'Envase dañado']];
        $action = app(CancelSaleAction::class);
        $preview = $action->preview($saleId, 'Error de carga', $dispositions);
        $key = (string) Str::uuid();
        $cancellationId = $action->execute($saleId, 'Error de carga', $dispositions, $key, $preview['fingerprint']);

        $this->assertSame($cancellationId, $action->execute($saleId, 'Error de carga', $dispositions, $key, $preview['fingerprint']));
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'status' => 'cancelled']);
        $this->assertDatabaseHas('sale_cancellations', ['sale_id' => $saleId, 'debt_relieved_cents' => 60000, 'refund_owed_cents' => 0]);
        $this->assertDatabaseHas('sale_cancellation_items', ['sale_item_id' => $itemId, 'quantity_usable' => 2, 'quantity_unusable' => 1]);
        $this->assertSame(2, (int) DB::table('stock_balances')->where('product_variant_id', $variant->id)->value('quantity_on_hand'));
        $this->assertSame(1, DB::table('stock_movements')->where('quantity_delta', 2)->count());
        Livewire::test(SalesHistory::class)->assertSee('Anulada')->call('openDetail', $saleId)->assertSee('Deuda cancelada');
    }

    public function test_paid_cancellation_records_refund_obligation_without_money_movement(): void
    {
        $variant = $this->stockedVariant(1);
        $saleId = app(CreateSaleAction::class)->execute([['variant_id' => $variant->id, 'quantity' => '1']], null, [['method' => 'transfer', 'amount' => '200', 'commission' => '0']], 'none', '0', (string) Str::uuid());
        $itemId = (int) DB::table('sale_items')->where('sale_id', $saleId)->value('id');
        $action = app(CancelSaleAction::class);
        $items = [$itemId => ['usable' => 1, 'reason' => '']];
        $preview = $action->preview($saleId, 'Pedido duplicado', $items);
        $action->execute($saleId, 'Pedido duplicado', $items, (string) Str::uuid(), $preview['fingerprint']);

        $this->assertDatabaseHas('sale_cancellations', ['sale_id' => $saleId, 'debt_relieved_cents' => 0, 'refund_owed_cents' => 20000]);
        $this->assertDatabaseCount('customer_payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
        Livewire::test(SalesHistory::class)->call('openDetail', $saleId)->assertSee('Reintegro pendiente');
    }

    public function test_modal_requires_inspection_and_unusable_reason_before_confirming(): void
    {
        $variant = $this->stockedVariant(1);
        $saleId = app(CreateSaleAction::class)->execute([['variant_id' => $variant->id, 'quantity' => '1']], Customer::factory()->create()->id, [], 'none', '0', (string) Str::uuid());
        $itemId = (int) DB::table('sale_items')->where('sale_id', $saleId)->value('id');

        Livewire::test(SalesHistory::class)
            ->call('openCancellation', $saleId)
            ->assertSee('Estado de las unidades')
            ->set('cancellationReason', 'Error de venta')
            ->call('reviewCancellation')
            ->assertHasErrors(['cancellationInspected'])
            ->set('cancellationInspected', true)
            ->set("cancellationItems.$itemId.usable", '0')
            ->call('reviewCancellation')
            ->assertHasErrors(['cancellationReason'])
            ->set("cancellationItems.$itemId.reason", 'Producto abierto')
            ->call('reviewCancellation')
            ->assertHasNoErrors()
            ->assertSet('cancellationPreview.usable_units', 0)
            ->call('confirmCancellation')
            ->assertSee('Venta anulada');

        $this->assertSame(0, (int) DB::table('stock_balances')->where('product_variant_id', $variant->id)->value('quantity_on_hand'));
        $this->assertDatabaseHas('sale_cancellation_items', ['sale_item_id' => $itemId, 'quantity_unusable' => 1, 'unusable_reason' => 'Producto abierto']);
    }

    private function stockedVariant(int $quantity): ProductVariant
    {
        $variant = ProductVariant::factory()->create();
        $order = app(PurchaseOrderService::class)->createDraft([
            'supplier_id' => Supplier::factory()->create()->id,
            'shipping_method' => 'quantity', 'general_shipping' => '0',
            'lines' => [['variant_id' => $variant->id, 'quantity' => (string) $quantity, 'unit_paid' => '100']],
        ]);
        app(PurchaseOrderService::class)->confirm($order->id);
        app(ReceivePurchaseOrderAction::class)->execute($order->id, [['order_line_id' => $order->lines->firstOrFail()->id, 'quantity' => (string) $quantity, 'unit_paid' => '100']], '0', 'cancel-sale-'.$variant->id);

        return $variant;
    }
}
