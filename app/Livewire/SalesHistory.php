<?php

namespace App\Livewire;

use App\Actions\Sales\CancelSaleAction;
use App\Actions\Sales\RecordCustomerPaymentAction;
use App\Actions\Sales\RecordRefundDisbursementAction;
use App\Models\Sale;
use App\Services\Pricing\Money;
use DateTimeInterface;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use RangeException;

class SalesHistory extends Component
{
    use WithPagination;

    private const DISPLAY_TIMEZONE = 'America/Argentina/Buenos_Aires';

    public string $search = '';

    public string $period = 'last30';

    #[Locked]
    public ?int $selectedSaleId = null;

    #[Locked]
    public ?int $collectionSaleId = null;

    public string $collectionMethod = 'cash';

    public string $collectionAmount = '';

    public string $collectionCommission = '0';

    public string $collectionTendered = '';

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $collectionPreview = null;

    #[Locked]
    public ?string $collectionKey = null;

    public ?string $collectionSuccess = null;

    #[Locked]
    public ?int $cancellationSaleId = null;

    public string $cancellationReason = '';

    /** @var array<int, array{usable: string, reason: string}> */
    public array $cancellationItems = [];

    public bool $cancellationInspected = false;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $cancellationPreview = null;

    #[Locked]
    public ?string $cancellationKey = null;

    public ?string $cancellationSuccess = null;

    #[Locked]
    public ?int $refundSaleId = null;

    public string $refundMethod = 'cash';

    public string $refundAmount = '';

    public string $refundPaidAt = '';

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $refundPreview = null;

    #[Locked]
    public ?string $refundKey = null;

    public ?string $refundSuccess = null;

    public function mount(): void
    {
        $saleId = request()->query('cobrar');
        if (! is_string($saleId) || ! ctype_digit($saleId)) {
            return;
        }

        $sale = Sale::query()->where('status', 'confirmed')->whereNotNull('customer_id')->find((int) $saleId);
        if ($sale === null) {
            return;
        }

        $this->period = 'all';
        $this->search = $sale->number;
        $this->openCollection($sale->id);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'refund') && ! in_array($property, ['refundPreview', 'refundSuccess'], true)) {
            $this->refundPreview = null;
            $this->resetValidation();
        }
        if (str_starts_with($property, 'cancellation') && ! in_array($property, ['cancellationPreview', 'cancellationSuccess'], true)) {
            $this->cancellationPreview = null;
            $this->resetValidation();
        }
        if (str_starts_with($property, 'collection') && ! in_array($property, ['collectionPreview', 'collectionSuccess'], true)) {
            $this->collectionPreview = null;
            $this->resetValidation();
        }
    }

    public function openDetail(int $saleId): void
    {
        Sale::query()->findOrFail($saleId);

        $this->selectedSaleId = $saleId;
        $this->dispatch('adara:open', id: 'sale-detail');
    }

    public function openRefund(int $saleId): void
    {
        $cancellation = DB::table('sale_cancellations')->where('sale_id', $saleId)->first();
        if ($cancellation === null) {
            $this->addError('refundAmount', 'Esta venta no tiene una anulación con reintegro.');

            return;
        }
        $paid = (int) DB::table('refund_disbursements')->where('sale_cancellation_id', $cancellation->id)->sum('amount_cents');
        $remaining = (int) $cancellation->refund_owed_cents - $paid;
        if ($remaining <= 0) {
            $this->addError('refundAmount', 'El reintegro de esta venta ya está completo.');

            return;
        }
        $this->reset('refundMethod', 'refundPreview', 'refundSuccess');
        $this->resetValidation();
        $this->refundSaleId = $saleId;
        $this->refundAmount = Money::fromCents($remaining)->toDecimal();
        $this->refundPaidAt = now()->setTimezone(self::DISPLAY_TIMEZONE)->format('Y-m-d\TH:i');
        $this->refundKey = (string) Str::uuid();
        $this->dispatch('adara:close', id: 'sale-detail');
        $this->dispatch('adara:open', id: 'sale-refund');
    }

    public function reviewRefund(RecordRefundDisbursementAction $refunds): void
    {
        $this->resetValidation();
        $this->refundPreview = null;
        try {
            $this->refundPreview = $refunds->preview($this->refundSaleId ?? 0, $this->refundMethod, $this->refundAmount, $this->refundPaidAt);
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('refundAmount', $exception->getMessage());
        }
    }

    public function confirmRefund(RecordRefundDisbursementAction $refunds): void
    {
        if ($this->refundPreview === null || $this->refundKey === null) {
            $this->addError('refundAmount', 'Revisá el reintegro antes de confirmarlo.');

            return;
        }
        try {
            $refunds->execute($this->refundSaleId ?? 0, $this->refundMethod, $this->refundAmount, $this->refundPaidAt, $this->refundKey, $this->refundPreview['fingerprint']);
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->refundPreview = null;
            $this->addError('refundAmount', $exception->getMessage());

            return;
        }
        $this->refundPreview = null;
        $this->refundSuccess = 'Reintegro registrado. El saldo pendiente se actualizó.';
    }

    public function openCancellation(int $saleId): void
    {
        $sale = Sale::query()->with('items')->where('status', 'confirmed')->findOrFail($saleId);
        $this->reset('cancellationReason', 'cancellationInspected', 'cancellationPreview', 'cancellationSuccess');
        $this->resetValidation();
        $this->cancellationSaleId = $saleId;
        $this->cancellationItems = $sale->items->mapWithKeys(fn ($item) => [$item->id => ['usable' => (string) $item->quantity, 'reason' => '']])->all();
        $this->cancellationKey = (string) Str::uuid();
        $this->dispatch('adara:close', id: 'sale-detail');
        $this->dispatch('adara:open', id: 'sale-cancellation');
    }

    public function reviewCancellation(CancelSaleAction $cancellations): void
    {
        $this->resetValidation();
        $this->cancellationPreview = null;
        if (! $this->cancellationInspected) {
            $this->addError('cancellationInspected', 'Confirmá que revisaste el estado de todas las unidades.');

            return;
        }
        try {
            $this->cancellationPreview = $cancellations->preview($this->cancellationSaleId ?? 0, $this->cancellationReason, $this->normalizedCancellationItems());
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('cancellationReason', $exception->getMessage());
        }
    }

    public function confirmCancellation(CancelSaleAction $cancellations): void
    {
        if ($this->cancellationPreview === null || $this->cancellationKey === null || ! $this->cancellationInspected) {
            $this->addError('cancellationReason', 'Revisá la anulación antes de confirmarla.');

            return;
        }
        try {
            $cancellations->execute($this->cancellationSaleId ?? 0, $this->cancellationReason, $this->normalizedCancellationItems(), $this->cancellationKey, $this->cancellationPreview['fingerprint']);
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->cancellationPreview = null;
            $this->addError('cancellationReason', $exception->getMessage());

            return;
        }
        $this->cancellationPreview = null;
        $this->cancellationSuccess = 'Venta anulada. El historial y los cobros originales se conservaron.';
    }

    /** @return array<int, array{usable: int, reason: string}> */
    private function normalizedCancellationItems(): array
    {
        $normalized = [];
        foreach ($this->cancellationItems as $itemId => $entry) {
            if (! ctype_digit((string) $entry['usable'])) {
                throw new InvalidArgumentException('Ingresá cantidades enteras aptas para volver al stock.');
            }
            $normalized[(int) $itemId] = ['usable' => (int) $entry['usable'], 'reason' => $entry['reason']];
        }

        return $normalized;
    }

    public function openCollection(int $saleId): void
    {
        $sale = Sale::query()->where('status', 'confirmed')->whereNotNull('customer_id')->findOrFail($saleId);
        $collected = (int) DB::table('payment_allocations')->where('sale_id', $saleId)->sum('amount_cents');

        if ($collected >= $sale->total_cents) {
            $this->addError('collectionAmount', 'Esta venta ya está cobrada.');

            return;
        }

        $this->reset('collectionMethod', 'collectionCommission', 'collectionTendered', 'collectionPreview', 'collectionSuccess');
        $this->resetValidation();
        $this->collectionSaleId = $saleId;
        $this->collectionAmount = Money::fromCents($sale->total_cents - $collected)->toDecimal();
        $this->collectionKey = (string) Str::uuid();
        $this->dispatch('adara:open', id: 'sale-collection');
    }

    public function reviewCollection(RecordCustomerPaymentAction $payments): void
    {
        $this->resetValidation();
        $this->collectionPreview = null;

        try {
            $this->collectionPreview = $payments->preview(
                $this->collectionSaleId ?? 0, $this->collectionMethod, $this->amountForCollection(),
                $this->commissionForCollection(), $this->cashTenderedForCollection(), true,
            );
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->showCollectionError($exception);
        }
    }

    public function confirmCollection(RecordCustomerPaymentAction $payments): void
    {
        if ($this->collectionPreview === null || $this->collectionKey === null) {
            $this->addError($this->collectionMethod === 'cash' ? 'collectionTendered' : 'collectionAmount', 'Revisá el cobro antes de confirmarlo.');

            return;
        }

        try {
            $payments->execute(
                $this->collectionSaleId ?? 0, $this->collectionMethod, $this->amountForCollection(),
                $this->commissionForCollection(), $this->cashTenderedForCollection(),
                true, $this->collectionKey, $this->collectionPreview['fingerprint'],
            );
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->collectionPreview = null;
            $this->showCollectionError($exception);

            return;
        }

        $this->collectionPreview = null;
        $this->collectionSuccess = 'Cobro registrado en la venta seleccionada.';
        $this->dispatch('adara:close', id: 'sale-detail');
    }

    private function showCollectionError(\Throwable $exception): void
    {
        foreach (['collectionAmount', 'collectionCommission', 'collectionTendered'] as $field) {
            $prefix = $field.': ';

            if (str_starts_with($exception->getMessage(), $prefix)) {
                $this->addError($field, substr($exception->getMessage(), strlen($prefix)));

                return;
            }
        }

        $this->addError($this->collectionMethod === 'cash' ? 'collectionTendered' : 'collectionAmount', $exception->getMessage());
    }

    private function cashTenderedForCollection(): ?string
    {
        if ($this->collectionMethod !== 'cash') {
            return null;
        }

        return $this->collectionTendered;
    }

    private function amountForCollection(): string
    {
        if ($this->collectionMethod !== 'cash') {
            return $this->collectionAmount;
        }

        $cashAmounts = $this->cashAmounts();

        if ($cashAmounts === null) {
            throw new InvalidArgumentException('collectionTendered: Ingresá el efectivo recibido, por ejemplo 20000,00.');
        }

        return Money::fromCents($cashAmounts['applied_cents'])->toDecimal();
    }

    private function commissionForCollection(): string
    {
        return $this->collectionMethod === 'cash' ? '0' : $this->collectionCommission;
    }

    /** @return array{balance_cents: int, applied_cents: int, change_cents: int}|null */
    private function cashAmounts(): ?array
    {
        if ($this->collectionMethod !== 'cash' || $this->collectionSaleId === null) {
            return null;
        }

        try {
            $receivedCents = Money::fromDecimal(str_replace(',', '.', trim($this->collectionTendered)))->cents;
        } catch (InvalidArgumentException|RangeException) {
            return null;
        }

        if ($receivedCents === 0) {
            return null;
        }

        $totalCents = DB::table('sales')->where('id', $this->collectionSaleId)->where('status', 'confirmed')->value('total_cents');

        if ($totalCents === null) {
            return null;
        }

        $collectedCents = (int) DB::table('payment_allocations')->where('sale_id', $this->collectionSaleId)->sum('amount_cents');
        $balanceCents = (int) $totalCents - $collectedCents;

        if ($balanceCents <= 0) {
            return null;
        }

        $appliedCents = min($receivedCents, $balanceCents);

        return ['balance_cents' => $balanceCents, 'applied_cents' => $appliedCents, 'change_cents' => $receivedCents - $appliedCents];
    }

    public function render(): View
    {
        $periodStart = $this->periodStart();
        $periodSales = Sale::query();

        if ($periodStart !== null) {
            $periodSales->where('confirmed_at', '>=', $periodStart);
        }

        $activeSales = (clone $periodSales)->where('status', 'confirmed');
        $totals = (clone $activeSales)->toBase()
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(total_cents), 0) as sales_cents, COALESCE(SUM(cost_total_snapshot_cents), 0) as cost_cents')
            ->firstOrFail();
        $unitsSold = DB::table('sale_items')
            ->whereIn('sale_id', (clone $activeSales)->select('id'))
            ->sum('quantity');

        $salesQuery = (clone $periodSales)->select('sales.*')
            ->selectSub(
                DB::table('payment_allocations')
                    ->selectRaw('COALESCE(SUM(amount_cents), 0)')
                    ->whereColumn('sale_id', 'sales.id'),
                'collected_cents',
            )
            ->withSum('items as units_sold', 'quantity');

        $term = trim($this->search);

        if ($term !== '') {
            $salesQuery->where(function (Builder $query) use ($term): void {
                $query->where('number', 'like', '%'.$term.'%')
                    ->orWhere('customer_name_snapshot', 'like', '%'.$term.'%')
                    ->orWhereHas('items', function (Builder $items) use ($term): void {
                        $items->where('sku_snapshot', 'like', '%'.$term.'%')
                            ->orWhere('product_name_snapshot', 'like', '%'.$term.'%');
                    });
            });
        }

        $sales = $salesQuery->orderByDesc('confirmed_at')->orderByDesc('id')->paginate(10);
        $saleIds = $sales->getCollection()->pluck('id');
        $paymentMethodsBySaleId = $saleIds->isEmpty() ? [] : DB::table('payment_allocations')
            ->join('customer_payments', 'customer_payments.id', '=', 'payment_allocations.customer_payment_id')
            ->whereIn('payment_allocations.sale_id', $saleIds)
            ->distinct()
            ->get(['payment_allocations.sale_id', 'customer_payments.method'])
            ->groupBy('sale_id')
            ->map(fn ($payments) => $payments->pluck('method')->all())
            ->all();

        $selectedSale = $this->selectedSaleId === null ? null : Sale::query()
            ->with('items')
            ->find($this->selectedSaleId);
        $selectedCancellation = $selectedSale === null ? null : DB::table('sale_cancellations')->where('sale_id', $selectedSale->id)->first();
        $selectedRefunds = $selectedCancellation === null ? collect() : DB::table('refund_disbursements')->where('sale_cancellation_id', $selectedCancellation->id)->orderBy('paid_at')->orderBy('id')->get();
        $selectedRefundRemainingCents = $selectedCancellation === null ? 0 : (int) $selectedCancellation->refund_owed_cents - (int) $selectedRefunds->sum('amount_cents');
        $selectedPayments = $selectedSale === null ? collect() : DB::table('payment_allocations')
            ->join('customer_payments', 'customer_payments.id', '=', 'payment_allocations.customer_payment_id')
            ->where('payment_allocations.sale_id', $selectedSale->id)
            ->orderBy('customer_payments.paid_at')
            ->orderBy('customer_payments.id')
            ->get(['payment_allocations.amount_cents', 'customer_payments.method', 'customer_payments.paid_at']);
        $collectionSale = $this->collectionSaleId === null ? null : Sale::query()->where('status', 'confirmed')->find($this->collectionSaleId);
        $collectionBalanceCents = $collectionSale === null ? null : $collectionSale->total_cents - (int) DB::table('payment_allocations')->where('sale_id', $collectionSale->id)->sum('amount_cents');
        $cancellationSale = $this->cancellationSaleId === null ? null : Sale::query()->with('items')->find($this->cancellationSaleId);

        return view('livewire.sales-history', [
            'sales' => $sales,
            'salesCount' => (int) $totals->sales_count,
            'salesCents' => (int) $totals->sales_cents,
            'grossMarginCents' => (int) $totals->sales_cents - (int) $totals->cost_cents,
            'unitsSold' => (int) $unitsSold,
            'paymentMethodsBySaleId' => $paymentMethodsBySaleId,
            'paymentMethodLabels' => [
                'cash' => 'Efectivo',
                'transfer' => 'Transferencia',
                'mercado_pago' => 'Mercado Pago',
                'card' => 'Tarjeta',
            ],
            'selectedSale' => $selectedSale,
            'selectedPayments' => $selectedPayments,
            'selectedCancellation' => $selectedCancellation,
            'selectedRefunds' => $selectedRefunds,
            'selectedRefundRemainingCents' => $selectedRefundRemainingCents,
            'cancellationSale' => $cancellationSale,
            'collectionSale' => $collectionSale,
            'collectionBalanceCents' => $collectionBalanceCents,
            'cashAmounts' => $this->cashAmounts(),
            'displayTimezone' => self::DISPLAY_TIMEZONE,
        ]);
    }

    private function periodStart(): ?DateTimeInterface
    {
        $localNow = now()->setTimezone(self::DISPLAY_TIMEZONE);

        return match ($this->period) {
            'all' => null,
            'month' => $localNow->startOfMonth()->setTimezone('UTC'),
            default => $localNow->subDays(29)->startOfDay()->setTimezone('UTC'),
        };
    }
}
