<?php

namespace App\Services\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReportService
{
    private const TIMEZONE = 'America/Argentina/Buenos_Aires';

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function bounds(string $from, string $to): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, self::TIMEZONE);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, self::TIMEZONE);

        if (! $start || ! $end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to || $start->greaterThan($end)) {
            throw new InvalidArgumentException('El período ingresado no es válido.');
        }

        return [$start->utc(), $end->addDay()->utc()];
    }

    /** @return array<string, mixed> */
    public function report(string $from, string $to): array
    {
        [$start, $end] = $this->bounds($from, $to);
        $utcFrom = $start->format('Y-m-d H:i:s');
        $utcTo = $end->format('Y-m-d H:i:s');

        $sales = DB::table('sales')->where('confirmed_at', '>=', $utcFrom)->where('confirmed_at', '<', $utcTo)
            ->select('id', 'confirmed_at', 'total_cents', 'discount_cents', 'cost_total_snapshot_cents')->get();
        $cancellations = DB::table('sale_cancellations as c')->join('sales as s', 's.id', '=', 'c.sale_id')
            ->where('c.cancelled_at', '>=', $utcFrom)->where('c.cancelled_at', '<', $utcTo)
            ->select('c.sale_id', 'c.cancelled_at', 's.total_cents', 's.discount_cents', 's.cost_total_snapshot_cents')->get();

        $grossSales = (int) $sales->sum('total_cents');
        $cancelledSales = (int) $cancellations->sum('total_cents');
        $soldCost = (int) $sales->sum('cost_total_snapshot_cents');
        $cancelledCost = (int) $cancellations->sum('cost_total_snapshot_cents');
        $netSales = $grossSales - $cancelledSales;
        $netCost = $soldCost - $cancelledCost;
        $grossProfit = $netSales - $netCost;

        $payment = DB::table('customer_payments')->where('paid_at', '>=', $utcFrom)->where('paid_at', '<', $utcTo)
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as collections, COALESCE(SUM(commission_cents), 0) as commissions')->first();
        $refundPaid = (int) DB::table('refund_disbursements')->where('paid_at', '>=', $utcFrom)->where('paid_at', '<', $utcTo)->sum('amount_cents');
        $expenses = (int) DB::table('business_expenses')->whereBetween('spent_on', [$from, $to])->sum('amount_cents');

        $receivables = (int) DB::table('sales as s')->where('s.status', 'confirmed')
            ->selectRaw('COALESCE(SUM(s.total_cents - COALESCE((SELECT SUM(pa.amount_cents) FROM payment_allocations pa WHERE pa.sale_id = s.id), 0)), 0) as balance')
            ->value('balance');
        $refundPending = (int) DB::table('sale_cancellations as c')
            ->selectRaw('COALESCE(SUM(c.refund_owed_cents - COALESCE((SELECT SUM(r.amount_cents) FROM refund_disbursements r WHERE r.sale_cancellation_id = c.id), 0)), 0) as balance')
            ->value('balance');

        $products = DB::table('sale_items as i')->join('sales as s', 's.id', '=', 'i.sale_id')
            ->leftJoin('sale_cancellations as c', 'c.sale_id', '=', 's.id')
            ->where(function ($query) use ($utcFrom, $utcTo) {
                $query->where(function ($sale) use ($utcFrom, $utcTo) {
                    $sale->where('s.confirmed_at', '>=', $utcFrom)->where('s.confirmed_at', '<', $utcTo);
                })->orWhere(function ($cancellation) use ($utcFrom, $utcTo) {
                    $cancellation->where('c.cancelled_at', '>=', $utcFrom)->where('c.cancelled_at', '<', $utcTo);
                });
            })
            ->select('i.product_variant_id', 'i.product_name_snapshot', 'i.presentation_snapshot', 'i.sku_snapshot', 'i.quantity', 'i.net_cents', 'i.cost_total_snapshot_cents', 's.confirmed_at', 'c.cancelled_at')->get();
        $productTotals = [];
        foreach ($products as $item) {
            $key = (int) $item->product_variant_id;
            $productTotals[$key] ??= ['name' => $item->product_name_snapshot, 'presentation' => $item->presentation_snapshot, 'sku' => $item->sku_snapshot, 'units' => 0, 'sales_cents' => 0, 'cost_cents' => 0];
            if ($item->confirmed_at >= $utcFrom && $item->confirmed_at < $utcTo) {
                $productTotals[$key]['units'] += (int) $item->quantity;
                $productTotals[$key]['sales_cents'] += (int) $item->net_cents;
                $productTotals[$key]['cost_cents'] += (int) $item->cost_total_snapshot_cents;
            }
            if ($item->cancelled_at !== null && $item->cancelled_at >= $utcFrom && $item->cancelled_at < $utcTo) {
                $productTotals[$key]['units'] -= (int) $item->quantity;
                $productTotals[$key]['sales_cents'] -= (int) $item->net_cents;
                $productTotals[$key]['cost_cents'] -= (int) $item->cost_total_snapshot_cents;
            }
        }
        $productTotals = array_filter($productTotals, fn (array $item): bool => $item['units'] !== 0 || $item['sales_cents'] !== 0);
        $categoryNames = DB::table('product_variants as v')
            ->join('product_groups as g', 'g.id', '=', 'v.product_group_id')
            ->leftJoin('categories as c', 'c.id', '=', 'g.category_id')
            ->whereIn('v.id', array_keys($productTotals))
            ->pluck('c.name', 'v.id');
        $categories = [];
        foreach ($productTotals as $variantId => $product) {
            $name = $categoryNames->get($variantId) ?? 'Sin categoría';
            $categories[$name] = ($categories[$name] ?? 0) + $product['sales_cents'];
        }
        arsort($categories);
        $productTotals = array_values($productTotals);
        usort($productTotals, fn (array $a, array $b): int => ($b['units'] <=> $a['units']) ?: ($b['sales_cents'] <=> $a['sales_cents']));

        $monthly = $start->diffInDays($end) > 62;
        $trend = [];
        foreach ($sales as $sale) {
            $key = CarbonImmutable::parse($sale->confirmed_at, 'UTC')->setTimezone(self::TIMEZONE)->format($monthly ? 'Y-m' : 'Y-m-d');
            $trend[$key] = ($trend[$key] ?? 0) + (int) $sale->total_cents;
        }
        foreach ($cancellations as $cancellation) {
            $key = CarbonImmutable::parse($cancellation->cancelled_at, 'UTC')->setTimezone(self::TIMEZONE)->format($monthly ? 'Y-m' : 'Y-m-d');
            $trend[$key] = ($trend[$key] ?? 0) - (int) $cancellation->total_cents;
        }
        ksort($trend);

        return [
            'gross_sales_cents' => $grossSales, 'cancelled_sales_cents' => $cancelledSales, 'net_sales_cents' => $netSales,
            'sold_cost_cents' => $soldCost, 'cancelled_cost_cents' => $cancelledCost, 'net_cost_cents' => $netCost,
            'gross_profit_cents' => $grossProfit, 'margin_percent' => $netSales > 0 ? round($grossProfit * 100 / $netSales, 1) : 0,
            'discounts_cents' => (int) $sales->sum('discount_cents') - (int) $cancellations->sum('discount_cents'),
            'collections_cents' => (int) $payment->collections, 'commissions_cents' => (int) $payment->commissions,
            'refunds_paid_cents' => $refundPaid, 'expenses_cents' => $expenses,
            'estimated_result_cents' => $grossProfit - (int) $payment->commissions - $expenses,
            'receivables_cents' => $receivables, 'refund_pending_cents' => $refundPending,
            'sales_count' => $sales->count(), 'cancelled_count' => $cancellations->count(),
            'products' => $productTotals, 'trend' => $trend, 'categories' => $categories,
        ];
    }
}
