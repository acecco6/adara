<?php

namespace Tests\Feature;

use App\Actions\Purchases\ReceivePurchaseOrderAction;
use App\Actions\Sales\CancelSaleAction;
use App\Actions\Sales\CreateSaleAction;
use App\Livewire\Reports\Index;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use App\Services\Reporting\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class EconomicReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_dashboard_and_report_show_real_zeroes(): void
    {
        $this->withoutVite();
        $this->get(route('home'))->assertOk()->assertSee('Todavía no hay ventas en este período')->assertSee('$ 0,00');
        $this->get(route('reports.index'))->assertOk()->assertSee('Reportes y resultados')->assertSee('No hay productos vendidos');
    }

    public function test_cancellation_affects_effective_month_without_rewriting_original_sale(): void
    {
        $this->travelTo(Carbon::parse('2026-09-30 23:30:00', 'UTC'));
        $variant = ProductVariant::factory()->create(['name' => '50 ml']);
        $order = app(PurchaseOrderService::class)->createDraft([
            'supplier_id' => Supplier::factory()->create()->id,
            'shipping_method' => 'quantity', 'general_shipping' => '0',
            'lines' => [['variant_id' => $variant->id, 'quantity' => '1', 'unit_paid' => '100']],
        ]);
        app(PurchaseOrderService::class)->confirm($order->id);
        app(ReceivePurchaseOrderAction::class)->execute($order->id, [['order_line_id' => $order->lines->firstOrFail()->id, 'quantity' => '1', 'unit_paid' => '100']], '0', 'report-'.$variant->id);
        $saleId = app(CreateSaleAction::class)->execute([['variant_id' => $variant->id, 'quantity' => '1']], null, [['method' => 'transfer', 'amount' => '200', 'commission' => '5']], 'none', '0', (string) Str::uuid());
        $september = app(ReportService::class)->report('2026-09-30', '2026-09-30');
        $this->assertSame(20000, $september['gross_sales_cents']);
        $this->assertSame(10000, $september['gross_profit_cents']);
        $this->assertSame(20000, $september['collections_cents']);
        $this->assertSame(500, $september['commissions_cents']);

        $this->travelTo(Carbon::parse('2026-10-01 13:00:00', 'UTC'));
        $itemId = (int) DB::table('sale_items')->where('sale_id', $saleId)->value('id');
        $items = [$itemId => ['usable' => 1, 'reason' => '']];
        $action = app(CancelSaleAction::class);
        $preview = $action->preview($saleId, 'Venta duplicada', $items);
        $action->execute($saleId, 'Venta duplicada', $items, (string) Str::uuid(), $preview['fingerprint']);

        $september = app(ReportService::class)->report('2026-09-30', '2026-09-30');
        $october = app(ReportService::class)->report('2026-10-01', '2026-10-01');
        $this->assertSame(20000, $september['net_sales_cents']);
        $this->assertSame(-20000, $october['net_sales_cents']);
        $this->assertSame(-10000, $october['gross_profit_cents']);
        $this->assertSame(20000, $october['refund_pending_cents']);
        $this->assertSame(0, $october['refunds_paid_cents']);
    }

    public function test_expense_is_recorded_and_reduces_estimated_result_only_on_its_date(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 12:00:00', 'UTC'));
        Livewire::test(Index::class)
            ->set('expenseConcept', 'Alquiler local')
            ->set('expenseCategory', 'Alquiler')
            ->set('expenseAmount', '1234,56')
            ->set('expenseDate', '2026-10-21')
            ->call('saveExpense')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('business_expenses', ['amount_cents' => 123456, 'spent_on' => '2026-10-21']);
        $this->assertSame(-123456, app(ReportService::class)->report('2026-10-21', '2026-10-21')['estimated_result_cents']);
        $this->assertSame(0, app(ReportService::class)->report('2026-10-22', '2026-10-22')['expenses_cents']);
    }

    public function test_date_bounds_use_argentina_timezone_and_reject_reversed_range(): void
    {
        $reports = app(ReportService::class);
        [$start, $end] = $reports->bounds('2026-10-22', '2026-10-22');
        $this->assertSame('2026-10-22 03:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-23 03:00:00', $end->format('Y-m-d H:i:s'));
        $this->expectException(\InvalidArgumentException::class);
        $reports->bounds('2026-10-23', '2026-10-22');
    }

    public function test_csv_escapes_spreadsheet_formulas_in_product_names(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 12:00:00', 'UTC'));
        $variant = ProductVariant::factory()->create();
        $variant->group->update(['name' => '=2+2']);
        $order = app(PurchaseOrderService::class)->createDraft([
            'supplier_id' => Supplier::factory()->create()->id,
            'shipping_method' => 'quantity', 'general_shipping' => '0',
            'lines' => [['variant_id' => $variant->id, 'quantity' => '1', 'unit_paid' => '100']],
        ]);
        app(PurchaseOrderService::class)->confirm($order->id);
        app(ReceivePurchaseOrderAction::class)->execute($order->id, [['order_line_id' => $order->lines->firstOrFail()->id, 'quantity' => '1', 'unit_paid' => '100']], '0', 'csv-'.$variant->id);
        app(CreateSaleAction::class)->execute([['variant_id' => $variant->id, 'quantity' => '1']], null, [['method' => 'cash', 'amount' => '200', 'commission' => '0', 'cash_tendered' => '200']], 'none', '0', (string) Str::uuid());

        $component = app(Index::class);
        $component->mount();
        $download = $component->export(app(ReportService::class));
        ob_start();
        $download->sendContent();
        $csv = ob_get_clean();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=2+2", $csv);
        $this->assertStringNotContainsString(';;=2+2;', $csv);
    }
}
