<?php

namespace Tests\Feature;

use App\Actions\Purchases\ReceivePurchaseOrderAction;
use App\Actions\Sales\CreateSaleAction;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class CreateSaleActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_recalculates_prices_and_saves_money_stock_and_history(): void
    {
        $first = $this->stockedVariant(3, '100', 'first');
        $second = $this->stockedVariant(2, '200', 'second');
        $lines = [
            ['variant_id' => $first->id, 'quantity' => '2', 'price' => '0.01'],
            ['variant_id' => $second->id, 'quantity' => '1'],
        ];
        $payments = [
            ['method' => 'cash', 'amount' => '100', 'commission' => '0', 'cash_tendered' => '200', 'change_confirmed' => true],
            ['method' => 'transfer', 'amount' => '600', 'commission' => '3'],
        ];

        $saleId = app(CreateSaleAction::class)->execute($lines, null, $payments, 'fixed', '100', 'sale-one');

        $this->assertDatabaseHas('sales', [
            'id' => $saleId, 'number' => 'VEN-000001', 'subtotal_cents' => 80000,
            'discount_cents' => 10000, 'total_cents' => 70000, 'cost_total_snapshot_10000' => 4000000,
            'initial_paid_cents' => 70000, 'initial_balance_cents' => 0,
        ]);
        $this->assertSame([40000, 40000], DB::table('sale_items')->where('sale_id', $saleId)->orderBy('line_position')->pluck('gross_cents')->all());
        $this->assertSame([5000, 5000], DB::table('sale_items')->where('sale_id', $saleId)->orderBy('line_position')->pluck('discount_cents')->all());
        $this->assertSame(1, $first->fresh()->balance->quantity_on_hand);
        $this->assertSame(1, $second->fresh()->balance->quantity_on_hand);
        $this->assertDatabaseHas('customer_payments', ['origin_sale_id' => $saleId, 'method' => 'cash', 'amount_cents' => 10000, 'cash_tendered_cents' => 20000, 'cash_change_cents' => 10000]);
        $this->assertDatabaseHas('customer_payments', ['origin_sale_id' => $saleId, 'method' => 'transfer', 'amount_cents' => 60000, 'commission_cents' => 300]);
        $this->assertSame(70000, DB::table('payment_allocations')->where('sale_id', $saleId)->sum('amount_cents'));
    }

    public function test_discount_remainder_goes_to_last_line_and_fiado_requires_customer(): void
    {
        $first = $this->stockedVariant(1, '100', 'discount-first');
        $last = $this->stockedVariant(1, '100', 'discount-last');
        $lines = [['variant_id' => $first->id, 'quantity' => '1'], ['variant_id' => $last->id, 'quantity' => '1']];

        try {
            app(CreateSaleAction::class)->execute($lines, null, [], 'fixed', '0.01', 'anonymous-debt');
            $this->fail('El fiado sin clienta debía ser rechazado.');
        } catch (DomainException) {
            $this->assertDatabaseCount('sales', 0);
        }

        $customer = Customer::factory()->create();
        $saleId = app(CreateSaleAction::class)->execute($lines, $customer->id, [], 'fixed', '0.01', 'identified-debt');

        $this->assertSame([0, 1], DB::table('sale_items')->where('sale_id', $saleId)->orderBy('line_position')->pluck('discount_cents')->all());
        $this->assertDatabaseHas('sales', ['id' => $saleId, 'customer_id' => $customer->id, 'discount_cents' => 1, 'initial_paid_cents' => 0, 'initial_balance_cents' => 39999]);
        $this->assertDatabaseCount('customer_payments', 0);
    }

    public function test_below_cost_needs_explicit_acceptance(): void
    {
        $variant = $this->stockedVariant(1, '100', 'below-cost');
        $lines = [['variant_id' => $variant->id, 'quantity' => '1']];
        $payments = [['method' => 'card', 'amount' => '80', 'commission' => '2']];

        try {
            app(CreateSaleAction::class)->execute($lines, null, $payments, 'percentage', '60', 'below-cost-sale');
            $this->fail('La pérdida debía requerir aceptación.');
        } catch (DomainException) {
            $this->assertDatabaseCount('sales', 0);
        }

        $saleId = app(CreateSaleAction::class)->execute($lines, null, $payments, 'percentage', '60', 'below-cost-sale', true);

        $this->assertDatabaseHas('sales', [
            'id' => $saleId, 'discount_value_decimal' => '60.0000',
            'discount_cents' => 12000, 'total_cents' => 8000, 'below_cost_accepted' => 1,
        ]);
        $this->assertSame(0, $variant->fresh()->balance->quantity_on_hand);
    }

    public function test_partial_initial_payment_records_debt_and_one_real_payment(): void
    {
        $variant = $this->stockedVariant(1, '100', 'partial-payment');
        $customer = Customer::factory()->create();

        $saleId = app(CreateSaleAction::class)->execute(
            [['variant_id' => $variant->id, 'quantity' => '1']], $customer->id,
            [['method' => 'mercado_pago', 'amount' => '50', 'commission' => '1.50']],
            'none', '0', 'partial-payment-sale',
        );

        $this->assertDatabaseHas('sales', ['id' => $saleId, 'total_cents' => 20000, 'initial_paid_cents' => 5000, 'initial_balance_cents' => 15000]);
        $this->assertDatabaseHas('customer_payments', ['origin_sale_id' => $saleId, 'method' => 'mercado_pago', 'amount_cents' => 5000, 'commission_cents' => 150]);
        $this->assertSame(5000, DB::table('payment_allocations')->where('sale_id', $saleId)->sum('amount_cents'));
    }

    public function test_cash_change_must_be_confirmed_before_stock_is_debited(): void
    {
        $variant = $this->stockedVariant(1, '100', 'cash-change');

        $this->expectException(DomainException::class);

        try {
            app(CreateSaleAction::class)->execute(
                [['variant_id' => $variant->id, 'quantity' => '1']], null,
                [['method' => 'cash', 'amount' => '200', 'commission' => '0', 'cash_tendered' => '500']],
                'none', '0', 'unconfirmed-change',
            );
        } finally {
            $this->assertDatabaseCount('sales', 0);
            $this->assertSame(1, $variant->fresh()->balance->quantity_on_hand);
        }
    }

    public function test_retry_does_not_duplicate_sale_or_stock_and_changed_request_is_rejected(): void
    {
        $variant = $this->stockedVariant(2, '100', 'idempotent');
        $lines = [['variant_id' => $variant->id, 'quantity' => '1']];
        $payments = [['method' => 'transfer', 'amount' => '200', 'commission' => '0']];
        $action = app(CreateSaleAction::class);

        $saleId = $action->execute($lines, null, $payments, 'none', '0', 'same-sale');
        $again = $action->execute($lines, null, $payments, 'none', '0', 'same-sale');

        $this->assertSame($saleId, $again);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('customer_payments', 1);
        $this->assertSame(1, $variant->fresh()->balance->quantity_on_hand);

        $this->expectException(InvalidArgumentException::class);
        $action->execute($lines, null, [], 'none', '0', 'same-sale');
    }

    public function test_insufficient_stock_rejects_all_lines(): void
    {
        $first = $this->stockedVariant(1, '100', 'limited-first');
        $second = $this->stockedVariant(1, '100', 'limited-second');

        try {
            app(CreateSaleAction::class)->execute(
                [['variant_id' => $first->id, 'quantity' => '1'], ['variant_id' => $second->id, 'quantity' => '2']],
                Customer::factory()->create()->id, [], 'none', '0', 'too-much-stock',
            );
            $this->fail('El stock insuficiente debía rechazar la venta.');
        } catch (DomainException) {
            $this->assertDatabaseCount('sales', 0);
            $this->assertSame(1, $first->fresh()->balance->quantity_on_hand);
            $this->assertSame(1, $second->fresh()->balance->quantity_on_hand);
        }
    }

    public function test_failure_on_second_movement_rolls_back_sale(): void
    {
        $first = $this->stockedVariant(1, '100', 'rollback-first');
        $second = $this->stockedVariant(1, '100', 'rollback-second');
        DB::statement("CREATE TRIGGER fail_sale_second_movement BEFORE INSERT ON stock_movements WHEN NEW.line_position = 1 AND (SELECT kind FROM stock_operations WHERE id = NEW.stock_operation_id) = 'sale' BEGIN SELECT RAISE(ABORT, 'simulated sale failure'); END");

        try {
            app(CreateSaleAction::class)->execute(
                [['variant_id' => $first->id, 'quantity' => '1'], ['variant_id' => $second->id, 'quantity' => '1']],
                Customer::factory()->create()->id, [], 'none', '0', 'rollback-sale',
            );
            $this->fail('El fallo debía revertir toda la venta.');
        } catch (QueryException) {
            $this->assertDatabaseCount('sales', 0);
            $this->assertDatabaseCount('sale_items', 0);
            $this->assertSame(1, $first->fresh()->balance->quantity_on_hand);
            $this->assertSame(1, $second->fresh()->balance->quantity_on_hand);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS fail_sale_second_movement');
        }
    }

    public function test_later_receipt_does_not_change_historical_cost(): void
    {
        $variant = $this->stockedVariant(1, '100', 'historic');
        $saleId = app(CreateSaleAction::class)->execute(
            [['variant_id' => $variant->id, 'quantity' => '1']], null,
            [['method' => 'transfer', 'amount' => '200', 'commission' => '0']],
            'none', '0', 'historic-sale',
        );

        $this->receive($variant, 1, '300', 'later-receipt');

        $this->assertSame(1000000, DB::table('sale_items')->where('sale_id', $saleId)->value('unit_cost_snapshot_10000'));
        $this->assertSame(3000000, $variant->fresh()->current_unit_cost_10000);
        $this->assertSame(60000, $variant->fresh()->current_price_cents);
    }

    public function test_sale_preserves_four_decimal_replacement_cost_from_shipping(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->receive($variant, 3, '100', 'fractional-cost', '0.01');

        $saleId = app(CreateSaleAction::class)->execute(
            [['variant_id' => $variant->id, 'quantity' => '1']], null,
            [['method' => 'transfer', 'amount' => '200', 'commission' => '0']],
            'none', '0', 'fractional-cost-sale',
        );

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $saleId,
            'unit_cost_snapshot_10000' => 1000033,
            'cost_total_snapshot_10000' => 1000033,
            'cost_total_snapshot_cents' => 10000,
        ]);
    }

    private function stockedVariant(int $quantity, string $unitCost, string $key): ProductVariant
    {
        $variant = ProductVariant::factory()->create();
        $this->receive($variant, $quantity, $unitCost, $key);

        return $variant;
    }

    private function receive(ProductVariant $variant, int $quantity, string $unitCost, string $key, string $shipping = '0'): void
    {
        $order = app(PurchaseOrderService::class)->createDraft([
            'supplier_id' => Supplier::factory()->create()->id,
            'shipping_method' => 'quantity',
            'general_shipping' => $shipping,
            'lines' => [['variant_id' => $variant->id, 'quantity' => (string) $quantity, 'unit_paid' => $unitCost]],
        ]);
        app(PurchaseOrderService::class)->confirm($order->id);
        app(ReceivePurchaseOrderAction::class)->execute(
            $order->id,
            [['order_line_id' => $order->lines->firstOrFail()->id, 'quantity' => (string) $quantity, 'unit_paid' => $unitCost]],
            '0', 'receipt-'.$key,
        );
    }
}
