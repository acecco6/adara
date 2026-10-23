<?php

namespace Tests\Feature;

use App\Actions\Purchases\ReceivePurchaseOrderAction;
use App\Livewire\SaleWorkspace;
use App\Models\Customer;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SaleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_catalog_shows_product_photo_only_when_it_exists(): void
    {
        $variant = $this->stockedVariant(1);

        Livewire::test(SaleWorkspace::class)
            ->assertSee('svg class="h-11 w-11"', false);

        $image = ProductImage::factory()->for($variant->group, 'group')->create();

        Livewire::test(SaleWorkspace::class)
            ->assertSee(route('products.images.show', $image), false)
            ->assertDontSee('svg class="h-11 w-11"', false);
    }

    public function test_cart_quotes_current_price_and_prepares_payment_without_saving_sale(): void
    {
        $variant = $this->stockedVariant(2);

        Livewire::test(SaleWorkspace::class)
            ->assertDontSee('Agregar cobro')
            ->assertDontSee('Revisar venta')
            ->set('search', $variant->sku)
            ->assertSee($variant->sku)
            ->call('addVariant', $variant->id)
            ->assertSet('cart.0.quantity', '1')
            ->assertSee('Agregar cobro')
            ->assertSee('Revisar venta')
            ->assertSee('$ 200,00')
            ->call('addPayment')
            ->call('fillRemaining', 0)
            ->assertSet('payments.0.amount', '200.00')
            ->set('cart.0.quantity', '2')
            ->assertSee('$ 400,00')
            ->call('addVariant', $variant->id)
            ->assertDispatched('adara:toast', tone: 'error', message: 'No hay más unidades disponibles de esta presentación.')
            ->assertSet('cart.0.quantity', '2');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(2, (int) DB::table('stock_balances')->where('product_variant_id', $variant->id)->value('quantity_on_hand'));
    }

    public function test_adding_a_visible_product_avoids_repeating_catalog_and_image_queries(): void
    {
        $variant = $this->stockedVariant(2);
        $component = Livewire::test(SaleWorkspace::class);
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $queries[] = $query->sql;
            }
        });

        $component->call('addVariant', $variant->id)->assertSet('cart.0.quantity', '1');

        $this->assertCount(4, $queries);

        $component->set('search', 'sin-coincidencias')
            ->assertSee($variant->group->name)
            ->assertSee('$ 200,00');
    }

    public function test_cart_keeps_line_when_stock_is_insufficient_and_blocks_unavailable_product(): void
    {
        $variant = $this->stockedVariant(1);

        Livewire::test(SaleWorkspace::class)
            ->call('addVariant', $variant->id)
            ->set('cart.0.quantity', '2')
            ->assertSee('sin stock suficiente')
            ->assertSet('cart.0.quantity', '2')
            ->call('removeVariant', $variant->id)
            ->assertSet('cart', []);

        DB::table('stock_balances')->where('product_variant_id', $variant->id)->update(['quantity_on_hand' => 0]);
        Livewire::test(SaleWorkspace::class)
            ->call('addVariant', $variant->id)
            ->assertSet('cart', [])
            ->assertDispatched('adara:toast', tone: 'error', message: 'Esta presentación no tiene stock o precio disponible.');
    }

    public function test_review_and_repeated_confirmation_create_one_sale_and_one_stock_movement(): void
    {
        $variant = $this->stockedVariant(2);

        $component = Livewire::test(SaleWorkspace::class)
            ->call('addVariant', $variant->id)
            ->call('addPayment')
            ->set('payments.0.method', 'transfer')
            ->call('fillRemaining', 0)
            ->call('reviewSale')
            ->assertSee('Confirmar esta venta')
            ->call('confirmSale')
            ->assertSet('confirmedSaleId', 1)
            ->assertSet('cart', [])
            ->assertSet('payments', [])
            ->assertSee('VEN-000001')
            ->assertSee('Tu venta está vacía')
            ->assertDontSee('La venta VEN-000001 se guardó correctamente');

        $component->call('confirmSale')->assertSet('confirmedSaleId', 1);

        $component->call('startAnotherSale')
            ->assertSet('confirmedSaleId', null)
            ->assertSee('Cancelar venta');

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('customer_payments', 1);
        $this->assertSame(1, (int) DB::table('stock_balances')->where('product_variant_id', $variant->id)->value('quantity_on_hand'));
        $this->assertSame(1, DB::table('stock_movements')->where('quantity_delta', -1)->count());
    }

    public function test_price_change_after_review_requires_a_new_review_before_confirmation(): void
    {
        $variant = $this->stockedVariant(1);
        $customer = Customer::factory()->create();

        $component = Livewire::test(SaleWorkspace::class)
            ->call('addVariant', $variant->id)
            ->set('customerId', (string) $customer->id)
            ->call('reviewSale');

        DB::table('product_variants')->where('id', $variant->id)->update(['current_price_cents' => 30000]);

        $component->call('confirmSale')->assertDispatched('adara:toast', fn (string $name, array $params): bool => str_contains($params['message'], 'cambió desde la revisión'));
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(1, (int) DB::table('stock_balances')->where('product_variant_id', $variant->id)->value('quantity_on_hand'));

        $component->call('reviewSale')->call('confirmSale')->assertSet('confirmedSaleId', 1);
        $this->assertDatabaseHas('sales', ['id' => 1, 'total_cents' => 30000, 'initial_balance_cents' => 30000]);
    }

    public function test_editing_the_cart_after_review_prevents_confirmation(): void
    {
        $variant = $this->stockedVariant(2);
        $customer = Customer::factory()->create();

        Livewire::test(SaleWorkspace::class)
            ->call('addVariant', $variant->id)
            ->set('customerId', (string) $customer->id)
            ->call('reviewSale')
            ->set('cart.0.quantity', '2')
            ->call('confirmSale')
            ->assertDispatched('adara:toast', tone: 'error', message: 'El carrito cambió. Revisá nuevamente la venta antes de confirmar.');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(2, (int) DB::table('stock_balances')->where('product_variant_id', $variant->id)->value('quantity_on_hand'));
    }

    public function test_cash_change_must_be_confirmed_and_is_saved_once(): void
    {
        $variant = $this->stockedVariant(1);

        $component = Livewire::test(SaleWorkspace::class)
            ->call('addVariant', $variant->id)
            ->call('addPayment')
            ->call('fillRemaining', 0)
            ->set('payments.0.cash_tendered', '300')
            ->call('reviewSale')
            ->assertDispatched('adara:toast', fn (string $name, array $params): bool => str_contains($params['message'], 'vuelto en efectivo debe calcularse y confirmarse'));

        $this->assertDatabaseCount('sales', 0);

        $component->set('payments.0.change_confirmed', true)
            ->call('reviewSale')
            ->assertSee('Vuelto: $ 100,00')
            ->call('confirmSale')
            ->assertSet('confirmedSaleId', 1);

        $this->assertDatabaseHas('customer_payments', [
            'origin_sale_id' => 1, 'method' => 'cash', 'amount_cents' => 20000,
            'cash_tendered_cents' => 30000, 'cash_change_cents' => 10000,
        ]);
    }

    public function test_below_cost_sale_requires_explicit_acceptance_in_confirmation(): void
    {
        $variant = $this->stockedVariant(1);

        $component = Livewire::test(SaleWorkspace::class)
            ->call('addVariant', $variant->id)
            ->set('discountType', 'fixed')
            ->set('discountValue', '150')
            ->call('addPayment')
            ->set('payments.0.method', 'card')
            ->call('fillRemaining', 0)
            ->call('reviewSale')
            ->call('confirmSale')
            ->assertDispatched('adara:toast', tone: 'error', message: 'Confirmá expresamente la venta debajo del costo de reposición.');

        $this->assertDatabaseCount('sales', 0);

        $component->set('acceptBelowCost', true)
            ->call('confirmSale')
            ->assertSet('confirmedSaleId', 1);

        $this->assertDatabaseHas('sales', ['id' => 1, 'total_cents' => 5000, 'below_cost_accepted' => 1]);
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
            '0', 'sale-workspace-'.$variant->id,
        );

        return $variant;
    }
}
