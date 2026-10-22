<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Debts extends Component
{
    use WithPagination;

    public string $search = '';

    #[Locked]
    public ?int $selectedCustomerId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selectedCustomerId = null;
    }

    public function selectCustomer(int $customerId): void
    {
        Customer::query()->findOrFail($customerId);
        $this->selectedCustomerId = $customerId;
    }

    public function render(): View
    {
        $allocations = DB::table('payment_allocations')
            ->selectRaw('sale_id, SUM(amount_cents) AS collected_cents')
            ->groupBy('sale_id');

        $base = DB::table('sales as s')
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->leftJoinSub($allocations, 'paid', fn ($join) => $join->on('paid.sale_id', '=', 's.id'))
            ->where('s.status', 'confirmed')
            ->whereRaw('s.total_cents > COALESCE(paid.collected_cents, 0)');

        $total = (clone $base)->selectRaw('COUNT(DISTINCT c.id) AS customers_count, COUNT(*) AS sales_count, COALESCE(SUM(s.total_cents - COALESCE(paid.collected_cents, 0)), 0) AS balance_cents')->first();

        $customers = (clone $base)
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $query->where(function (Builder $names): void {
                    $names->where('c.name', 'like', '%'.trim($this->search).'%')
                        ->orWhere('c.phone', 'like', '%'.trim($this->search).'%');
                });
            })
            ->selectRaw('c.id, c.name, c.phone, c.is_active, COUNT(s.id) AS sales_count, SUM(s.total_cents - COALESCE(paid.collected_cents, 0)) AS balance_cents, MIN(s.confirmed_at) AS oldest_sale_at')
            ->groupBy('c.id', 'c.name', 'c.phone', 'c.is_active')
            ->orderByDesc('balance_cents')->orderBy('c.name')->paginate(15);

        $selectedCustomer = $this->selectedCustomerId === null ? null : Customer::query()->find($this->selectedCustomerId);
        $selectedSales = $selectedCustomer === null ? collect() : (clone $base)
            ->where('s.customer_id', $selectedCustomer->id)
            ->selectRaw('s.id, s.number, s.confirmed_at, s.total_cents, COALESCE(paid.collected_cents, 0) AS collected_cents, s.total_cents - COALESCE(paid.collected_cents, 0) AS balance_cents')
            ->orderBy('s.confirmed_at')->orderBy('s.id')->get();

        return view('livewire.customers.debts', [
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer,
            'selectedSales' => $selectedSales,
            'totalCustomers' => (int) $total->customers_count,
            'totalSales' => (int) $total->sales_count,
            'totalBalanceCents' => (int) $total->balance_cents,
        ]);
    }
}
