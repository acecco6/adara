<?php

namespace Tests\Feature;

use App\Actions\Purchases\ReceivePurchaseOrderAction;
use App\Actions\Sales\CancelSaleAction;
use App\Actions\Sales\CreateSaleAction;
use App\Livewire\Customers\Debts;
use App\Livewire\Payments\Index as PaymentsIndex;
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

class CustomerDebtsAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_debts_group_only_active_unpaid_sales_and_link_to_the_chosen_sale(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 12:00:00', 'UTC'));
        $variant = $this->stockedVariant(3);
        $customer = Customer::factory()->create(['name' => 'Clienta de prueba']);
        $other = Customer::factory()->create(['name' => 'Sin deuda']);
        $firstId = $this->sale($variant, $customer, []);
        $secondId = $this->sale($variant, $customer, [['method' => 'transfer', 'amount' => '100', 'commission' => '0']]);
        $this->sale($variant, $other, [['method' => 'transfer', 'amount' => '200', 'commission' => '0']]);

        Livewire::test(Debts::class)
            ->assertSee('Clienta de prueba')
            ->assertDontSee('Sin deuda')
            ->assertSee('$ 300,00')
            ->call('selectCustomer', $customer->id)
            ->assertSee(route('sales.index', ['cobrar' => $firstId]), false)
            ->assertSee(route('sales.index', ['cobrar' => $secondId]), false);

        $this->withoutVite();
        $this->get(route('sales.index', ['cobrar' => $firstId]))
            ->assertOk()
            ->assertSee('Saldo de esta venta')
            ->assertSee('Registrar cobro');

        $itemId = (int) DB::table('sale_items')->where('sale_id', $firstId)->value('id');
        $items = [$itemId => ['usable' => 1, 'reason' => '']];
        $action = app(CancelSaleAction::class);
        $preview = $action->preview($firstId, 'Venta duplicada', $items);
        $action->execute($firstId, 'Venta duplicada', $items, (string) Str::uuid(), $preview['fingerprint']);

        Livewire::test(Debts::class)->assertSee('$ 100,00')->assertDontSee('$ 300,00');
    }

    public function test_payments_are_counted_once_on_their_argentina_payment_date(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 02:30:00', 'UTC'));
        $variant = $this->stockedVariant(1);
        $customer = Customer::factory()->create();
        $saleId = $this->sale($variant, $customer, [['method' => 'transfer', 'amount' => '100', 'commission' => '5']]);
        $number = DB::table('sales')->where('id', $saleId)->value('number');

        Livewire::test(PaymentsIndex::class)
            ->set('from', '2026-10-21')->set('to', '2026-10-21')
            ->assertSee($number)
            ->assertSee('$ 100,00')
            ->assertSee('$ 5,00')
            ->assertSee('21/10/2026');

        Livewire::test(PaymentsIndex::class)
            ->set('from', '2026-10-22')->set('to', '2026-10-22')
            ->assertDontSee($number)
            ->assertSee('No hay cobros para las fechas');

        $this->withoutVite();
        $this->get(route('customers.index'))->assertOk()->assertSee('Ver clientas con deuda')->assertSee('Ver cobros');
        $this->get(route('customers.debts'))->assertOk()->assertSee('Clientas con deuda');
        $this->get(route('payments.index'))->assertOk()->assertSee('Cobros recibidos');
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
        app(ReceivePurchaseOrderAction::class)->execute($order->id, [['order_line_id' => $order->lines->firstOrFail()->id, 'quantity' => (string) $quantity, 'unit_paid' => '100']], '0', 'debt-'.$variant->id);

        return $variant;
    }

    /** @param array<int, array<string, string>> $payments */
    private function sale(ProductVariant $variant, Customer $customer, array $payments): int
    {
        return app(CreateSaleAction::class)->execute([['variant_id' => $variant->id, 'quantity' => '1']], $customer->id, $payments, 'none', '0', (string) Str::uuid());
    }
}
