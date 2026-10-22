<?php

namespace App\Livewire\Dashboard;

use App\Services\Reporting\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Index extends Component
{
    public string $period = 'last30';

    public function render(ReportService $reports): View
    {
        $today = CarbonImmutable::now('America/Argentina/Buenos_Aires');
        $from = match ($this->period) {
            'today' => $today,
            'month' => $today->startOfMonth(),
            default => $today->subDays(29),
        };
        $data = $reports->report($from->format('Y-m-d'), $today->format('Y-m-d'));
        $lowStock = DB::table('product_variants as v')
            ->join('product_groups as g', 'g.id', '=', 'v.product_group_id')
            ->leftJoin('stock_balances as b', 'b.product_variant_id', '=', 'v.id')
            ->where('v.is_active', true)
            ->whereRaw('COALESCE(b.quantity_on_hand, 0) <= v.minimum_stock')
            ->orderByRaw('COALESCE(b.quantity_on_hand, 0) ASC')
            ->limit(5)
            ->get(['v.sku', 'v.name', 'v.minimum_stock', 'g.name as product_name', 'b.quantity_on_hand']);
        $recentSales = DB::table('sales')->orderByDesc('confirmed_at')->limit(5)
            ->get(['number', 'status', 'total_cents', 'confirmed_at']);

        return view('livewire.dashboard.index', [
            'report' => $data,
            'lowStock' => $lowStock,
            'recentSales' => $recentSales,
            'stockUnits' => (int) DB::table('stock_balances')->sum('quantity_on_hand'),
            'stockCost' => (int) DB::table('stock_balances as b')->join('product_variants as v', 'v.id', '=', 'b.product_variant_id')
                ->selectRaw('COALESCE(SUM((b.quantity_on_hand * v.current_unit_cost_10000) / 100), 0) as cents')->value('cents'),
        ]);
    }
}
