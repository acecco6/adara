<?php

namespace App\Livewire\Payments;

use App\Services\Reporting\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $from = '';

    public string $to = '';

    public string $method = 'all';

    public function mount(): void
    {
        $today = CarbonImmutable::now('America/Argentina/Buenos_Aires');
        $this->from = $today->subDays(29)->format('Y-m-d');
        $this->to = $today->format('Y-m-d');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to', 'method'], true)) {
            $this->resetPage();
            $this->resetValidation();
        }
    }

    public function render(ReportService $reports): View
    {
        $methods = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'mercado_pago' => 'Mercado Pago', 'card' => 'Tarjeta'];
        $payments = null;
        $totals = null;

        try {
            [$start, $end] = $reports->bounds($this->from, $this->to);
            if ($this->method !== 'all' && ! array_key_exists($this->method, $methods)) {
                throw new InvalidArgumentException('El medio de pago no es válido.');
            }

            $query = DB::table('customer_payments as p')
                ->leftJoin('customers as c', 'c.id', '=', 'p.customer_id')
                ->where('p.paid_at', '>=', $start->format('Y-m-d H:i:s'))
                ->where('p.paid_at', '<', $end->format('Y-m-d H:i:s'))
                ->when($this->method !== 'all', fn ($query) => $query->where('p.method', $this->method));

            $totals = (clone $query)->selectRaw('COUNT(*) AS count, COALESCE(SUM(p.amount_cents), 0) AS amount_cents, COALESCE(SUM(p.commission_cents), 0) AS commission_cents')->first();
            $payments = $query->select('p.id', 'p.paid_at', 'p.method', 'p.amount_cents', 'p.commission_cents', 'p.cash_tendered_cents', 'p.cash_change_cents', 'c.name as customer_name')
                ->selectSub(DB::table('payment_allocations as a')->join('sales as s', 's.id', '=', 'a.sale_id')
                    ->whereColumn('a.customer_payment_id', 'p.id')->selectRaw("GROUP_CONCAT(s.number, ', ')"), 'sale_numbers')
                ->orderByDesc('p.paid_at')->orderByDesc('p.id')->paginate(20);
        } catch (InvalidArgumentException) {
            $this->addError('to', 'Elegí un período y un medio de pago válidos.');
        }

        return view('livewire.payments.index', [
            'payments' => $payments,
            'totals' => $totals,
            'methods' => $methods,
        ]);
    }
}
