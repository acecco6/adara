<?php

namespace Tests\Feature;

use App\Actions\Purchases\ClosePurchaseOrderAction;
use App\Actions\Purchases\ReceivePurchaseOrderAction;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\InitialStockService;
use App\Services\Pricing\DecimalNumber;
use App\Services\PurchaseOrderService;
use DomainException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class ReceivePurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_adds_stock_and_replaces_replacement_cost_and_price_using_eighty_percent(): void
    {
        $variant = ProductVariant::factory()->create();
        app(InitialStockService::class)->record($variant->id, '3', '50', 'initial-before-receipt');
        $order = $this->confirmedOrder([[$variant, '2', '100']], '10');
        $lines = $this->receivedLines($order);

        $receiptId = app(ReceivePurchaseOrderAction::class)->execute($order->id, $lines, '4', 'receipt-one');

        $this->assertDatabaseHas('purchase_receipts', [
            'id' => $receiptId,
            'purchase_order_id' => $order->id,
            'general_shipping_applied_cents' => 1000,
            'additional_shipping_cents' => 400,
        ]);
        $this->assertDatabaseHas('purchase_receipt_lines', [
            'purchase_receipt_id' => $receiptId,
            'quantity_received' => 2,
            'merchandise_paid_cents' => 20000,
            'general_shipping_applied_cents' => 1000,
            'additional_shipping_applied_cents' => 400,
            'unit_cost_10000' => 1070000,
            'price_cents' => 20000,
            'markup_decimal' => '80',
        ]);
        $this->assertSame(5, $variant->fresh()->balance->quantity_on_hand);
        $this->assertSame(1070000, $variant->fresh()->current_unit_cost_10000);
        $this->assertSame(20000, $variant->fresh()->current_price_cents);
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'quantity_delta' => 2,
            'quantity_after' => 5,
            'unit_cost_10000' => 1070000,
        ]);
        $this->assertSame('received', $order->fresh()->status);
    }

    public function test_repeating_same_confirmation_does_not_duplicate_stock_or_shipping(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = $this->confirmedOrder([[$variant, '3', '100']], '1');
        $lines = $this->receivedLines($order);
        $action = app(ReceivePurchaseOrderAction::class);

        $first = $action->execute($order->id, $lines, '2', 'repeat-receipt');
        $second = $action->execute($order->id, $lines, '2.00', 'repeat-receipt');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('purchase_receipts', 1);
        $this->assertDatabaseCount('purchase_receipt_lines', 1);
        $this->assertDatabaseCount('stock_operations', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(3, $variant->fresh()->balance->quantity_on_hand);
        $this->assertDatabaseHas('purchase_receipts', ['id' => $first, 'general_shipping_applied_cents' => 100, 'additional_shipping_cents' => 200]);
    }

    public function test_shipping_uses_stable_order_and_actual_paid_costs(): void
    {
        $first = ProductVariant::factory()->create();
        $last = ProductVariant::factory()->create();
        $order = $this->confirmedOrder([[$first, '2', '100'], [$last, '1', '100']], '10');
        $lines = $this->receivedLines($order);
        $lines[0]['unit_paid'] = '200';
        $lines[1]['unit_paid'] = '300';

        $receiptId = app(ReceivePurchaseOrderAction::class)->execute($order->id, $lines, '0', 'ordered-allocation', true);
        $saved = DB::table('purchase_receipt_lines')->where('purchase_receipt_id', $receiptId)->orderBy('line_position')->get();

        $this->assertSame([666, 334], $saved->pluck('general_shipping_applied_cents')->all());
        $this->assertSame([40000, 30000], $saved->pluck('merchandise_paid_cents')->all());
        $this->assertSame(2, $first->fresh()->balance->quantity_on_hand);
        $this->assertSame(1, $last->fresh()->balance->quantity_on_hand);
        $this->assertSame(2033300, $first->fresh()->current_unit_cost_10000);
        $this->assertSame(3033400, $last->fresh()->current_unit_cost_10000);
    }

    public function test_merchandise_cost_method_uses_actual_cost_as_weight(): void
    {
        $first = ProductVariant::factory()->create();
        $second = ProductVariant::factory()->create();
        $order = $this->confirmedOrder([[$first, '1', '100'], [$second, '1', '100']], '4', 'merchandise_cost');
        $lines = $this->receivedLines($order);
        $lines[1]['unit_paid'] = '300';

        $receiptId = app(ReceivePurchaseOrderAction::class)->execute($order->id, $lines, '0', 'weighted-shipping', true);
        $shares = DB::table('purchase_receipt_lines')->where('purchase_receipt_id', $receiptId)
            ->orderBy('line_position')->pluck('general_shipping_applied_cents')->all();

        $this->assertSame([100, 300], $shares);
        $this->assertSame(1010000, $first->fresh()->current_unit_cost_10000);
        $this->assertSame(3030000, $second->fresh()->current_unit_cost_10000);
    }

    public function test_database_failure_in_second_line_rolls_back_receipt_and_first_stock_change(): void
    {
        $first = ProductVariant::factory()->create();
        $second = ProductVariant::factory()->create();
        $order = $this->confirmedOrder([[$first, '1', '100'], [$second, '1', '200']], '3');
        $movementInsertCount = 0;
        DB::listen(function (QueryExecuted $query) use (&$movementInsertCount): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert') && str_contains($query->sql, 'stock_movements')) {
                $movementInsertCount++;

                if ($movementInsertCount === 2) {
                    throw new QueryException($query->connectionName, $query->sql, $query->bindings, new RuntimeException('Simulated stock movement failure.'));
                }
            }
        });

        try {
            app(ReceivePurchaseOrderAction::class)->execute($order->id, $this->receivedLines($order), '0', 'rollback-receipt');
            $this->fail('El fallo de la segunda línea debía revertir la transacción.');
        } catch (QueryException) {
            $this->assertDatabaseCount('purchase_receipts', 0);
            $this->assertDatabaseCount('purchase_receipt_lines', 0);
            $this->assertDatabaseCount('stock_operations', 0);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertSame(0, $first->fresh()->balance->quantity_on_hand);
            $this->assertSame(0, $second->fresh()->balance->quantity_on_hand);
            $this->assertNull($first->fresh()->current_unit_cost_10000);
            $this->assertNull($first->fresh()->current_price_cents);
            $this->assertSame('confirmed', $order->fresh()->status);
        }
    }

    public function test_changed_real_cost_requires_explicit_confirmation(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = $this->confirmedOrder([[$variant, '1', '100']], '0');
        $lines = $this->receivedLines($order);
        $lines[0]['unit_paid'] = '101';

        try {
            app(ReceivePurchaseOrderAction::class)->execute($order->id, $lines, '0', 'cost-change');
            $this->fail('El cambio de costo debía exigir una confirmación adicional.');
        } catch (DomainException) {
            $this->assertDatabaseCount('purchase_receipts', 0);
            $this->assertSame(0, $variant->fresh()->balance->quantity_on_hand);
        }

        $receiptId = app(ReceivePurchaseOrderAction::class)->execute($order->id, $lines, '0', 'cost-change', true);

        $this->assertDatabaseHas('purchase_receipt_lines', ['purchase_receipt_id' => $receiptId, 'unit_paid_cents' => 10100]);
        $this->assertDatabaseHas('purchase_receipts', ['id' => $receiptId, 'cost_change_accepted' => 1]);
    }

    public function test_duplicate_presentation_lines_use_one_combined_replacement_cost(): void
    {
        $variant = ProductVariant::factory()->create(['markup_override_decimal' => '50']);
        $order = $this->confirmedOrder([[$variant, '1', '100'], [$variant, '1', '300']], '0');

        $receiptId = app(ReceivePurchaseOrderAction::class)->execute($order->id, $this->receivedLines($order), '0', 'same-sku');

        $this->assertSame(2, $variant->fresh()->balance->quantity_on_hand);
        $this->assertSame(2000000, $variant->fresh()->current_unit_cost_10000);
        $this->assertSame(30000, $variant->fresh()->current_price_cents);
        $this->assertSame([1, 2], DB::table('stock_movements')->orderBy('line_position')->pluck('quantity_after')->all());
        $this->assertSame([2000000, 2000000], DB::table('purchase_receipt_lines')->where('purchase_receipt_id', $receiptId)->orderBy('line_position')->pluck('unit_cost_10000')->all());
    }

    public function test_two_partial_receipts_complete_order_without_duplicating_general_shipping(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = $this->confirmedOrder([[$variant, '2', '100']], '10');
        $lines = $this->receivedLines($order);
        $lines[0]['quantity'] = '1';

        $action = app(ReceivePurchaseOrderAction::class);
        $first = $action->execute($order->id, $lines, '0', 'partial-first');
        $this->assertSame('partially_received', $order->fresh()->status);
        $this->assertSame(1, $variant->fresh()->balance->quantity_on_hand);
        $this->assertDatabaseHas('purchase_receipts', ['id' => $first, 'general_shipping_applied_cents' => 500]);

        $second = $action->execute($order->id, $lines, '0', 'partial-second');
        $this->assertSame('received', $order->fresh()->status);
        $this->assertDatabaseHas('purchase_receipts', ['id' => $second, 'general_shipping_applied_cents' => 500]);
        $this->assertSame(2, $variant->fresh()->balance->quantity_on_hand);
        $this->assertSame(1050000, $variant->fresh()->current_unit_cost_10000);

        try {
            $action->execute($order->id, $lines, '0', 'different-key');
            $this->fail('Una recepción tras completar el pedido debía rechazarse.');
        } catch (DomainException) {
            $this->assertDatabaseCount('purchase_receipts', 2);
            $this->assertSame(2, $variant->fresh()->balance->quantity_on_hand);
        }
    }

    public function test_two_receipts_match_a_single_receipt_in_quantity_cost_and_shipping(): void
    {
        $firstVariant = ProductVariant::factory()->create();
        $secondVariant = ProductVariant::factory()->create();
        $singleOrder = $this->confirmedOrder([[$firstVariant, '2', '100'], [$secondVariant, '1', '300']], '0.03', 'merchandise_cost');
        $singleLines = $this->receivedLines($singleOrder);
        $singleId = app(ReceivePurchaseOrderAction::class)->execute($singleOrder->id, $singleLines, '0', 'single-equivalent');
        $singleShares = DB::table('purchase_receipt_lines')->where('purchase_receipt_id', $singleId)
            ->orderBy('line_position')->pluck('general_shipping_applied_cents')->all();
        $singleCosts = [$firstVariant->fresh()->current_unit_cost_10000, $secondVariant->fresh()->current_unit_cost_10000];
        $splitFirst = ProductVariant::factory()->create();
        $splitSecond = ProductVariant::factory()->create();

        $splitOrder = $this->confirmedOrder([[$splitFirst, '2', '100'], [$splitSecond, '1', '300']], '0.03', 'merchandise_cost');
        $splitLines = $this->receivedLines($splitOrder);
        $firstReceipt = app(ReceivePurchaseOrderAction::class)->execute($splitOrder->id, [$splitLines[0]], '0', 'split-first');
        $secondReceipt = app(ReceivePurchaseOrderAction::class)->execute($splitOrder->id, [$splitLines[1]], '0', 'split-last');

        $this->assertSame('received', $splitOrder->fresh()->status);
        $this->assertSame(3, DB::table('purchase_receipts')->where('purchase_order_id', $splitOrder->id)->sum('general_shipping_applied_cents'));
        $this->assertSame($singleShares, [
            DB::table('purchase_receipt_lines')->where('purchase_receipt_id', $firstReceipt)->value('general_shipping_applied_cents'),
            DB::table('purchase_receipt_lines')->where('purchase_receipt_id', $secondReceipt)->value('general_shipping_applied_cents'),
        ]);
        $this->assertSame($singleCosts, [$splitFirst->fresh()->current_unit_cost_10000, $splitSecond->fresh()->current_unit_cost_10000]);
        $this->assertSame(2, $splitFirst->fresh()->balance->quantity_on_hand);
        $this->assertSame(1, $splitSecond->fresh()->balance->quantity_on_hand);
    }

    public function test_overdelivery_requires_confirmation_and_preserves_original_order_quantity(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = $this->confirmedOrder([[$variant, '2', '100']], '3');
        $lines = $this->receivedLines($order);
        $lines[0]['quantity'] = '3';

        try {
            app(ReceivePurchaseOrderAction::class)->execute($order->id, $lines, '0', 'extra-rejected');
            $this->fail('La sobreentrega debía exigir confirmación.');
        } catch (DomainException) {
            $this->assertDatabaseCount('purchase_receipts', 0);
        }

        $receiptId = app(ReceivePurchaseOrderAction::class)->execute($order->id, $lines, '0', 'extra-accepted', false, true);
        $this->assertSame(2, $order->fresh()->lines->firstOrFail()->quantity_ordered);
        $this->assertSame('received', $order->fresh()->status);
        $this->assertSame(3, $variant->fresh()->balance->quantity_on_hand);
        $this->assertDatabaseHas('purchase_order_quantity_extensions', [
            'purchase_receipt_id' => $receiptId,
            'purchase_order_line_id' => $order->lines->firstOrFail()->id,
            'extra_quantity' => 1,
        ]);
    }

    public function test_overdelivery_after_a_partial_receipt_keeps_pending_nonnegative(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = $this->confirmedOrder([[$variant, '3', '100']], '0');
        $lines = $this->receivedLines($order);
        $lines[0]['quantity'] = '2';
        $action = app(ReceivePurchaseOrderAction::class);
        $action->execute($order->id, $lines, '0', 'delivery-one');
        $lines[0]['quantity'] = '2';

        $secondId = $action->execute($order->id, $lines, '0', 'delivery-two-extra', false, true);

        $this->assertSame(3, $order->fresh()->lines->firstOrFail()->quantity_ordered);
        $this->assertSame(4, $variant->fresh()->balance->quantity_on_hand);
        $this->assertSame('received', $order->fresh()->status);
        $this->assertDatabaseHas('purchase_order_quantity_extensions', ['purchase_receipt_id' => $secondId, 'extra_quantity' => 1]);
    }

    public function test_canceling_pending_units_records_unassigned_shipping_without_repricing_received_units(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = $this->confirmedOrder([[$variant, '2', '100']], '10');
        $lines = $this->receivedLines($order);
        $lines[0]['quantity'] = '1';
        app(ReceivePurchaseOrderAction::class)->execute($order->id, $lines, '0', 'before-close');
        $costBefore = $variant->fresh()->current_unit_cost_10000;

        $closureId = app(ClosePurchaseOrderAction::class)->execute($order->id, 'El proveedor no entregará la unidad restante');

        $this->assertSame($closureId, app(ClosePurchaseOrderAction::class)->execute($order->id, 'El proveedor no entregará la unidad restante'));
        $this->assertSame('closed_partial', $order->fresh()->status);
        $this->assertDatabaseHas('purchase_order_closures', ['id' => $closureId, 'unassigned_general_shipping_cents' => 500]);
        $this->assertDatabaseHas('purchase_order_canceled_lines', ['purchase_order_closure_id' => $closureId, 'quantity_canceled' => 1]);
        $this->assertSame($costBefore, $variant->fresh()->current_unit_cost_10000);
        $this->assertSame(1, $variant->fresh()->balance->quantity_on_hand);

        $this->expectException(DomainException::class);
        app(ReceivePurchaseOrderAction::class)->execute($order->id, $lines, '0', 'after-closure');
    }

    public function test_invalid_key_or_inactive_presentation_cannot_change_stock(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = $this->confirmedOrder([[$variant, '1', '100']], '0');
        $lines = $this->receivedLines($order);
        $action = app(ReceivePurchaseOrderAction::class);

        try {
            $action->execute($order->id, $lines, '0', '');
            $this->fail('La clave vacía debía rechazarse.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('purchase_receipts', 0);
        }

        $variant->update(['is_active' => false]);

        try {
            $action->execute($order->id, $lines, '0', 'inactive-variant');
            $this->fail('La presentación inactiva debía rechazarse.');
        } catch (DomainException) {
            $this->assertDatabaseCount('purchase_receipts', 0);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertSame(0, $variant->fresh()->balance->quantity_on_hand);
        }
    }

    public function test_reused_key_with_changed_amount_is_rejected_and_history_is_immutable(): void
    {
        $variant = ProductVariant::factory()->create();
        $order = $this->confirmedOrder([[$variant, '1', '100']], '0');
        $lines = $this->receivedLines($order);
        $action = app(ReceivePurchaseOrderAction::class);
        $receiptId = $action->execute($order->id, $lines, '0', 'unchanged-key');

        try {
            $action->execute($order->id, $lines, '1', 'unchanged-key');
            $this->fail('La clave reutilizada con otro importe debía rechazarse.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('purchase_receipts', 1);
        }

        $this->expectException(QueryException::class);
        DB::table('purchase_receipts')->where('id', $receiptId)->update(['additional_shipping_cents' => 1]);
    }

    /** @param list<array{0: ProductVariant, 1: string, 2: string}> $lines */
    private function confirmedOrder(array $lines, string $generalShipping, string $shippingMethod = 'quantity'): PurchaseOrder
    {
        $order = app(PurchaseOrderService::class)->createDraft([
            'supplier_id' => Supplier::factory()->create()->id,
            'shipping_method' => $shippingMethod,
            'general_shipping' => $generalShipping,
            'lines' => array_map(fn (array $line): array => [
                'variant_id' => $line[0]->id,
                'quantity' => $line[1],
                'unit_paid' => $line[2],
            ], $lines),
        ]);

        return app(PurchaseOrderService::class)->confirm($order->id);
    }

    /** @return list<array{order_line_id: int, quantity: string, unit_paid: string}> */
    private function receivedLines(PurchaseOrder $order): array
    {
        return $order->lines->map(fn ($line): array => [
            'order_line_id' => $line->id,
            'quantity' => (string) $line->quantity_ordered,
            'unit_paid' => DecimalNumber::formatUnsigned($line->unit_paid_cents, 2),
        ])->all();
    }
}
