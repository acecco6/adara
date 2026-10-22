<?php

namespace App\Actions\Purchases;

use App\Services\Pricing\DecimalNumber;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ClosePurchaseOrderAction
{
    public function execute(int $orderId, string $reason): int
    {
        $reason = trim($reason);

        if ($orderId <= 0 || $reason === '' || Str::length($reason) > 1000) {
            throw new InvalidArgumentException('Indicá un motivo de cierre de hasta 1000 caracteres.');
        }

        return DB::transaction(function () use ($orderId, $reason): int {
            $existing = DB::table('purchase_order_closures')->where('purchase_order_id', $orderId)->first();

            if ($existing !== null) {
                if ($existing->reason !== $reason) {
                    throw new InvalidArgumentException('El pedido ya se cerró con otro motivo.');
                }

                return (int) $existing->id;
            }

            $order = DB::table('purchase_orders')->where('id', $orderId)->first();

            if ($order === null || $order->status !== 'partially_received') {
                throw new DomainException('Solo se puede cancelar el pendiente de un pedido parcialmente recibido.');
            }

            $received = DB::table('purchase_receipt_lines')
                ->join('purchase_receipts', 'purchase_receipts.id', '=', 'purchase_receipt_lines.purchase_receipt_id')
                ->where('purchase_receipts.purchase_order_id', $orderId)
                ->select('purchase_receipt_lines.purchase_order_line_id')
                ->selectRaw('SUM(purchase_receipt_lines.quantity_received) as quantity')
                ->groupBy('purchase_receipt_lines.purchase_order_line_id')
                ->get()->keyBy('purchase_order_line_id');
            $extensions = DB::table('purchase_order_quantity_extensions')
                ->join('purchase_receipts', 'purchase_receipts.id', '=', 'purchase_order_quantity_extensions.purchase_receipt_id')
                ->where('purchase_receipts.purchase_order_id', $orderId)
                ->select('purchase_order_quantity_extensions.purchase_order_line_id')
                ->selectRaw('SUM(purchase_order_quantity_extensions.extra_quantity) as quantity')
                ->groupBy('purchase_order_quantity_extensions.purchase_order_line_id')
                ->get()->keyBy('purchase_order_line_id');
            $allocated = (int) DB::table('purchase_receipts')->where('purchase_order_id', $orderId)->sum('general_shipping_applied_cents');
            $unassigned = (int) $order->general_shipping_cents - $allocated;

            if ($unassigned < 0) {
                throw new DomainException('El envío asignado supera el envío general del pedido.');
            }

            $canceled = [];

            foreach (DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->orderBy('line_position')->get() as $line) {
                $lineId = (int) $line->id;
                $extended = $extensions->has($lineId) ? (int) $extensions->get($lineId)->quantity : 0;
                $receivedQuantity = $received->has($lineId) ? (int) $received->get($lineId)->quantity : 0;
                $authorized = DecimalNumber::checkedAdd((int) $line->quantity_ordered, $extended);
                $pending = $authorized - $receivedQuantity;

                if ($pending < 0) {
                    throw new DomainException('El historial recibido supera la cantidad autorizada.');
                }

                if ($pending > 0) {
                    $canceled[] = ['purchase_order_line_id' => $lineId, 'quantity_canceled' => $pending];
                }
            }

            if ($canceled === []) {
                throw new DomainException('El pedido no tiene unidades pendientes para cancelar.');
            }

            $closureId = DB::table('purchase_order_closures')->insertGetId([
                'purchase_order_id' => $orderId,
                'reason' => $reason,
                'unassigned_general_shipping_cents' => $unassigned,
                'closed_at' => now(),
                'created_at' => now(),
            ]);

            foreach ($canceled as $line) {
                DB::table('purchase_order_canceled_lines')->insert($line + ['purchase_order_closure_id' => $closureId]);
            }

            DB::table('purchase_orders')->where('id', $orderId)->update(['status' => 'closed_partial', 'updated_at' => now()]);

            return $closureId;
        });
    }
}
