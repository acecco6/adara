<?php

namespace Tests\Feature;

use App\Actions\Purchases\ReceivePurchaseOrderAction;
use App\Actions\Sales\CancelSaleAction;
use App\Actions\Sales\CreateSaleAction;
use App\Actions\Sales\RecordRefundDisbursementAction;
use App\Livewire\SalesHistory;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class RefundDisbursementTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_refunds_are_recorded_once_with_the_actual_method(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 12:00:30', 'UTC'));
        $saleId = $this->cancelPaidSale();
        $refunds = app(RecordRefundDisbursementAction::class);
        $date = '2026-10-22T09:00';
        $firstPreview = $refunds->preview($saleId, 'cash', '25', $date);
        $key = (string) Str::uuid();
        $firstId = $refunds->execute($saleId, 'cash', '25', $date, $key, $firstPreview['fingerprint']);
        $this->assertSame($firstId, $refunds->execute($saleId, 'cash', '25', $date, $key, $firstPreview['fingerprint']));
        $this->assertSame(17500, $firstPreview['remaining_cents']);
        $secondPreview = $refunds->preview($saleId, 'transfer', '175', $date);
        $refunds->execute($saleId, 'transfer', '175', $date, (string) Str::uuid(), $secondPreview['fingerprint']);

        $this->assertDatabaseCount('refund_disbursements', 2);
        $this->assertDatabaseHas('refund_disbursements', ['method' => 'cash', 'amount_cents' => 2500]);
        $this->assertDatabaseCount('customer_payments', 1);
        Livewire::test(SalesHistory::class)->call('openDetail', $saleId)->assertSee('Reintegros realizados')->assertSee('Reintegro pendiente');
    }

    public function test_overpayment_and_date_before_cancellation_are_rejected(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 12:00:30', 'UTC'));
        $saleId = $this->cancelPaidSale();
        $refunds = app(RecordRefundDisbursementAction::class);
        try {
            $refunds->preview($saleId, 'cash', '200.01', '2026-10-22T09:00');
            $this->fail('Debía rechazar el sobrepago.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('supera', $exception->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        $refunds->preview($saleId, 'cash', '10', '2026-10-21T09:00');
    }

    public function test_modal_registers_partial_refund(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 12:00:30', 'UTC'));
        $saleId = $this->cancelPaidSale();
        Livewire::test(SalesHistory::class)
            ->call('openDetail', $saleId)
            ->assertSee('Registrar reintegro')
            ->call('openRefund', $saleId)
            ->assertSee('Importe devuelto en ARS')
            ->set('refundAmount', '50')
            ->set('refundMethod', 'mercado_pago')
            ->call('reviewRefund')
            ->assertHasNoErrors()
            ->assertSet('refundPreview.remaining_cents', 15000)
            ->call('confirmRefund')
            ->assertSee('Reintegro registrado');
        $this->assertDatabaseHas('refund_disbursements', ['method' => 'mercado_pago', 'amount_cents' => 5000]);
    }

    public function test_old_review_cannot_confirm_after_another_refund_changes_balance(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 12:00:30', 'UTC'));
        $saleId = $this->cancelPaidSale();
        $refunds = app(RecordRefundDisbursementAction::class);
        $date = '2026-10-22T09:00';
        $stale = $refunds->preview($saleId, 'cash', '100', $date);
        $other = $refunds->preview($saleId, 'transfer', '50', $date);
        $refunds->execute($saleId, 'transfer', '50', $date, (string) Str::uuid(), $other['fingerprint']);

        $this->expectException(\DomainException::class);
        $refunds->execute($saleId, 'cash', '100', $date, (string) Str::uuid(), $stale['fingerprint']);
    }

    private function cancelPaidSale(): int
    {
        $variant = ProductVariant::factory()->create();
        $order = app(PurchaseOrderService::class)->createDraft([
            'supplier_id' => Supplier::factory()->create()->id,
            'shipping_method' => 'quantity', 'general_shipping' => '0',
            'lines' => [['variant_id' => $variant->id, 'quantity' => '1', 'unit_paid' => '100']],
        ]);
        app(PurchaseOrderService::class)->confirm($order->id);
        app(ReceivePurchaseOrderAction::class)->execute($order->id, [['order_line_id' => $order->lines->firstOrFail()->id, 'quantity' => '1', 'unit_paid' => '100']], '0', 'refund-'.$variant->id);
        $saleId = app(CreateSaleAction::class)->execute([['variant_id' => $variant->id, 'quantity' => '1']], null, [['method' => 'transfer', 'amount' => '200', 'commission' => '0']], 'none', '0', (string) Str::uuid());
        $itemId = (int) DB::table('sale_items')->where('sale_id', $saleId)->value('id');
        $dispositions = [$itemId => ['usable' => 1, 'reason' => '']];
        $cancellations = app(CancelSaleAction::class);
        $review = $cancellations->preview($saleId, 'Venta duplicada', $dispositions);
        $cancellations->execute($saleId, 'Venta duplicada', $dispositions, (string) Str::uuid(), $review['fingerprint']);
        $this->assertDatabaseCount('refund_disbursements', 0);

        return $saleId;
    }
}
