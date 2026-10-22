<?php

namespace Tests\Feature;

use App\Actions\Purchases\ReceivePurchaseOrderAction;
use App\Actions\Sales\CreateSaleAction;
use App\Actions\Sales\RecordCustomerPaymentAction;
use App\Livewire\SalesHistory;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SalesHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_page_shows_a_real_empty_state(): void
    {
        $this->get(route('sales.index'))
            ->assertOk()
            ->assertSee('Historial de ventas')
            ->assertSee('Aún no hay ventas en este período')
            ->assertSee('0 ventas vigentes');
    }

    public function test_detail_rejects_an_unknown_sale(): void
    {
        Livewire::test(SalesHistory::class)
            ->call('openDetail', 999)
            ->assertNotFound();
    }

    public function test_history_and_detail_keep_original_price_cost_and_product_names(): void
    {
        $variant = $this->stockedVariant(2);
        $saleId = $this->recordSale($variant, null, [[
            'method' => 'transfer', 'amount' => '200', 'commission' => '5',
        ]]);
        $saleNumber = 'VEN-'.str_pad((string) $saleId, 6, '0', STR_PAD_LEFT);
        $originalName = $variant->group->name;

        DB::table('product_variants')->where('id', $variant->id)->update([
            'current_price_cents' => 35000,
            'current_unit_cost_10000' => 1750000,
        ]);
        $variant->group->update(['name' => 'Producto renombrado']);

        Livewire::test(SalesHistory::class)
            ->assertSee($saleNumber)
            ->assertSee('$ 200,00')
            ->assertSee('$ 100,00')
            ->assertSee('Transferencia')
            ->call('openDetail', $saleId)
            ->assertSee('Costo histórico de productos')
            ->assertSee($originalName)
            ->assertSee($variant->sku)
            ->assertDontSee('Producto renombrado');

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'total_cents' => 20000,
            'cost_total_snapshot_cents' => 10000,
        ]);
    }

    public function test_history_separates_unpaid_balance_from_sale_total_and_filters_by_customer_and_sku(): void
    {
        $variant = $this->stockedVariant(1);
        $customer = Customer::factory()->create(['name' => 'María López']);
        $saleId = $this->recordSale($variant, $customer->id, []);

        Livewire::test(SalesHistory::class)
            ->assertSee('María López')
            ->assertSee('Sin cobros')
            ->assertSee('Saldo $ 200,00')
            ->set('search', $variant->sku)
            ->assertSee('VEN-'.str_pad((string) $saleId, 6, '0', STR_PAD_LEFT))
            ->set('search', 'María')
            ->assertSee('María López')
            ->set('search', 'No existe')
            ->assertSee('No encontramos ventas');

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'initial_paid_cents' => 0,
            'initial_balance_cents' => 20000,
        ]);
    }

    public function test_period_filter_changes_sales_and_summary_without_changing_history(): void
    {
        $this->travelTo(Carbon::parse('2026-08-01 12:00:00'));
        $variant = $this->stockedVariant(2);
        $oldSale = $this->recordSale($variant, null, [[
            'method' => 'transfer', 'amount' => '200', 'commission' => '0',
        ]]);
        $this->travelTo(Carbon::parse('2026-10-22 12:00:00'));
        $currentSale = $this->recordSale($variant, null, [[
            'method' => 'transfer', 'amount' => '200', 'commission' => '0',
        ]]);

        Livewire::test(SalesHistory::class)
            ->assertSee('VEN-'.str_pad((string) $currentSale, 6, '0', STR_PAD_LEFT))
            ->assertDontSee('VEN-'.str_pad((string) $oldSale, 6, '0', STR_PAD_LEFT))
            ->assertSee('1 venta vigente')
            ->set('period', 'all')
            ->assertSee('VEN-'.str_pad((string) $oldSale, 6, '0', STR_PAD_LEFT))
            ->assertSee('2 ventas vigentes')
            ->assertSee('$ 400,00');

        $this->assertDatabaseCount('sales', 2);
    }

    public function test_month_filter_and_display_use_argentina_calendar_days(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 01:30:00', 'UTC'));
        $variant = $this->stockedVariant(2);
        $septemberSale = $this->recordSale($variant, null, [[
            'method' => 'transfer', 'amount' => '200', 'commission' => '0',
        ]]);
        $this->travelTo(Carbon::parse('2026-10-01 03:30:00', 'UTC'));
        $octoberSale = $this->recordSale($variant, null, [[
            'method' => 'transfer', 'amount' => '200', 'commission' => '0',
        ]]);

        Livewire::test(SalesHistory::class)
            ->set('period', 'month')
            ->assertSee('VEN-'.str_pad((string) $octoberSale, 6, '0', STR_PAD_LEFT))
            ->assertDontSee('VEN-'.str_pad((string) $septemberSale, 6, '0', STR_PAD_LEFT))
            ->assertSee('01/10/2026')
            ->set('period', 'all')
            ->assertSee('30/09/2026');
    }

    public function test_later_collection_only_changes_the_selected_sale(): void
    {
        $variant = $this->stockedVariant(2);
        $customer = Customer::factory()->create();
        $firstSale = $this->recordSale($variant, $customer->id, []);
        $secondSale = $this->recordSale($variant, $customer->id, []);

        Livewire::test(SalesHistory::class)
            ->call('openCollection', $secondSale)
            ->set('collectionMethod', 'transfer')
            ->set('collectionAmount', '200,00')
            ->set('collectionCommission', '3,00')
            ->call('reviewCollection')
            ->assertSet('collectionPreview.allocations', [[
                'sale_id' => $secondSale, 'number' => 'VEN-'.str_pad((string) $secondSale, 6, '0', STR_PAD_LEFT), 'amount_cents' => 20000,
            ]])
            ->call('confirmCollection')
            ->assertSee('Cobro registrado')
            ->assertSee('Saldo $ 200,00');

        $payment = DB::table('customer_payments')->whereNull('origin_sale_id')->first();
        $this->assertNull($payment);
        $payment = DB::table('customer_payments')->where('origin_sale_id', $secondSale)->firstOrFail();
        $this->assertSame(20000, $payment->amount_cents);
        $this->assertSame(300, $payment->commission_cents);
        $this->assertDatabaseHas('payment_allocations', ['customer_payment_id' => $payment->id, 'sale_id' => $secondSale, 'amount_cents' => 20000]);
        $this->assertDatabaseMissing('payment_allocations', ['customer_payment_id' => $payment->id, 'sale_id' => $firstSale]);
        $this->assertSame(0, DB::table('payment_allocations')->where('sale_id', $firstSale)->sum('amount_cents'));
    }

    public function test_selected_sale_rejects_an_amount_that_would_fit_only_across_multiple_sales(): void
    {
        $variant = $this->stockedVariant(2);
        $customer = Customer::factory()->create();
        $this->recordSale($variant, $customer->id, []);
        $selectedSale = $this->recordSale($variant, $customer->id, []);

        Livewire::test(SalesHistory::class)
            ->call('openCollection', $selectedSale)
            ->set('collectionMethod', 'transfer')
            ->set('collectionAmount', '300')
            ->call('reviewCollection')
            ->assertHasErrors(['collectionAmount'])
            ->assertSee('supera el saldo pendiente de esta venta');

        $this->assertDatabaseCount('customer_payments', 0);
    }

    public function test_collection_rejects_overpayment_and_changed_debt_without_writing(): void
    {
        $variant = $this->stockedVariant(1);
        $customer = Customer::factory()->create();
        $saleId = $this->recordSale($variant, $customer->id, []);
        $payments = app(RecordCustomerPaymentAction::class);

        $this->expectException(\DomainException::class);
        $payments->preview($saleId, 'transfer', '201', '0', null, false);
    }

    public function test_cash_received_above_sale_balance_calculates_change_automatically(): void
    {
        $variant = $this->stockedVariant(1);
        $customer = Customer::factory()->create();
        $saleId = $this->recordSale($variant, $customer->id, []);

        Livewire::test(SalesHistory::class)
            ->call('openCollection', $saleId)
            ->assertSet('collectionTendered', '')
            ->assertDontSee('Importe a aplicar en ARS')
            ->assertDontSee('Comisión real en ARS')
            ->set('collectionTendered', '250')
            ->assertSee('Vuelto a entregar')
            ->assertSee('Se aplica a esta venta')
            ->assertSee('$ 50,00')
            ->call('reviewCollection')
            ->assertSet('collectionPreview.cash_change_cents', 5000)
            ->assertSet('collectionPreview.amount_cents', 20000)
            ->assertSee('Confirmar cobro y vuelto')
            ->call('confirmCollection');

        $this->assertDatabaseHas('customer_payments', [
            'origin_sale_id' => $saleId, 'method' => 'cash', 'amount_cents' => 20000,
            'cash_tendered_cents' => 25000, 'cash_change_cents' => 5000,
        ]);
    }

    public function test_cash_received_below_sale_balance_records_a_partial_payment(): void
    {
        $variant = $this->stockedVariant(1);
        $customer = Customer::factory()->create();
        $saleId = $this->recordSale($variant, $customer->id, []);

        Livewire::test(SalesHistory::class)
            ->call('openCollection', $saleId)
            ->assertSet('collectionAmount', '200.00')
            ->assertSet('collectionTendered', '')
            ->assertSee('Efectivo recibido en ARS')
            ->assertSee('Vuelto a entregar')
            ->set('collectionTendered', '150')
            ->call('reviewCollection')
            ->assertHasNoErrors()
            ->assertSet('collectionPreview.amount_cents', 15000)
            ->assertSet('collectionPreview.cash_change_cents', 0)
            ->call('confirmCollection')
            ->assertSee('Saldo $ 50,00');

        $this->assertDatabaseHas('customer_payments', [
            'origin_sale_id' => $saleId, 'amount_cents' => 15000,
            'cash_tendered_cents' => 15000, 'cash_change_cents' => 0,
        ]);
    }

    public function test_cash_collection_requires_a_received_amount(): void
    {
        $variant = $this->stockedVariant(1);
        $customer = Customer::factory()->create();
        $saleId = $this->recordSale($variant, $customer->id, []);

        Livewire::test(SalesHistory::class)
            ->call('openCollection', $saleId)
            ->assertSee('Efectivo recibido en ARS')
            ->set('collectionTendered', '')
            ->call('reviewCollection')
            ->assertHasErrors(['collectionTendered'])
            ->assertSee('Ingresá el efectivo recibido')
            ->assertDontSee('decimal canónico')
            ->set('collectionTendered', '250.0')
            ->call('reviewCollection')
            ->assertHasNoErrors()
            ->assertSet('collectionPreview.cash_change_cents', 5000);
    }

    public function test_paid_sale_cannot_open_collection(): void
    {
        $variant = $this->stockedVariant(1);
        $customer = Customer::factory()->create();
        $saleId = $this->recordSale($variant, $customer->id, [[
            'method' => 'transfer', 'amount' => '200', 'commission' => '0',
        ]]);

        Livewire::test(SalesHistory::class)
            ->call('openCollection', $saleId)
            ->assertHasErrors(['collectionAmount']);

        $this->assertDatabaseCount('customer_payments', 1);
    }

    public function test_paid_sale_cannot_be_used_to_collect_another_debt(): void
    {
        $variant = $this->stockedVariant(2);
        $customer = Customer::factory()->create();
        $paidSale = $this->recordSale($variant, $customer->id, [[
            'method' => 'transfer', 'amount' => '200', 'commission' => '0',
        ]]);
        $this->recordSale($variant, $customer->id, []);

        $this->expectException(\DomainException::class);
        app(RecordCustomerPaymentAction::class)->preview($paidSale, 'transfer', '100', '0', null, false);
    }

    public function test_collection_confirmation_is_idempotent_and_rechecks_preview(): void
    {
        $variant = $this->stockedVariant(2);
        $customer = Customer::factory()->create();
        $firstSale = $this->recordSale($variant, $customer->id, []);
        $secondSale = $this->recordSale($variant, $customer->id, []);
        $payments = app(RecordCustomerPaymentAction::class);
        $preview = $payments->preview($secondSale, 'transfer', '100', '0', null, false);
        $key = (string) Str::uuid();
        $paymentId = $payments->execute($secondSale, 'transfer', '200', '0', null, false, $key, $payments->preview($secondSale, 'transfer', '200', '0', null, false)['fingerprint']);

        $this->assertSame($paymentId, $payments->execute($secondSale, 'transfer', '200', '0', null, false, $key, $preview['fingerprint']));
        $this->assertDatabaseCount('customer_payments', 1);

        $this->expectException(\DomainException::class);
        $payments->execute($secondSale, 'transfer', '100', '0', null, false, (string) Str::uuid(), $preview['fingerprint']);
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
        app(ReceivePurchaseOrderAction::class)->execute(
            $order->id,
            [['order_line_id' => $order->lines->firstOrFail()->id, 'quantity' => (string) $quantity, 'unit_paid' => '100']],
            '0', 'sales-history-'.$variant->id,
        );

        return $variant;
    }

    /** @param list<array<string, string>> $payments */
    private function recordSale(ProductVariant $variant, ?int $customerId, array $payments): int
    {
        return app(CreateSaleAction::class)->execute(
            [['variant_id' => $variant->id, 'quantity' => '1']],
            $customerId, $payments, 'none', '0', (string) Str::uuid(),
        );
    }
}
