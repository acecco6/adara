<?php

namespace App\Actions\Sales;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CancelSaleAction
{
    /** @param array<int, array{usable: int, reason: string}> $dispositions
     * @return array{debt_relieved_cents: int, refund_owed_cents: int, usable_units: int, fingerprint: string}
     */
    public function preview(int $saleId, string $reason, array $dispositions): array
    {
        $reason = trim($reason);
        if ($reason === '' || Str::length($reason) > 500) {
            throw new InvalidArgumentException('Ingresá un motivo de anulación de hasta 500 caracteres.');
        }
        $sale = DB::table('sales')->where('id', $saleId)->where('status', 'confirmed')->first();
        if ($sale === null) {
            throw new DomainException('La venta ya no está disponible para anular.');
        }
        $items = DB::table('sale_items')->where('sale_id', $saleId)->orderBy('line_position')->get();
        if ($items->isEmpty() || count($dispositions) !== $items->count()) {
            throw new InvalidArgumentException('Revisá el estado de todas las unidades vendidas.');
        }
        $normalized = [];
        $usableUnits = 0;
        foreach ($items as $item) {
            $entry = $dispositions[$item->id] ?? null;
            if (! is_array($entry) || ! isset($entry['usable'], $entry['reason']) || ! is_int($entry['usable']) || ! is_string($entry['reason']) || $entry['usable'] < 0 || $entry['usable'] > $item->quantity) {
                throw new InvalidArgumentException('La cantidad apta debe estar entre cero y la cantidad vendida.');
            }
            $unusable = (int) $item->quantity - $entry['usable'];
            $unusableReason = trim($entry['reason']);
            if ($unusable > 0 && ($unusableReason === '' || Str::length($unusableReason) > 500)) {
                throw new InvalidArgumentException('Explicá por qué las unidades no aptas no vuelven al stock.');
            }
            $normalized[$item->id] = ['usable' => $entry['usable'], 'unusable' => $unusable, 'reason' => $unusable > 0 ? $unusableReason : ''];
            $usableUnits += $entry['usable'];
        }
        $collected = (int) DB::table('payment_allocations')->where('sale_id', $saleId)->sum('amount_cents');
        if ($collected > $sale->total_cents) {
            throw new DomainException('Los cobros de esta venta exceden el total. Revisá el historial antes de anular.');
        }

        return [
            'debt_relieved_cents' => (int) $sale->total_cents - $collected,
            'refund_owed_cents' => $collected,
            'usable_units' => $usableUnits,
            'fingerprint' => hash('sha256', json_encode([$saleId, $reason, $normalized, $collected], JSON_THROW_ON_ERROR)),
        ];
    }

    /** @param array<int, array{usable: int, reason: string}> $dispositions */
    public function execute(int $saleId, string $reason, array $dispositions, string $idempotencyKey, string $expectedFingerprint): int
    {
        if (trim($idempotencyKey) === '' || Str::length($idempotencyKey) > 128) {
            throw new InvalidArgumentException('La clave de confirmación no es válida.');
        }

        return DB::transaction(function () use ($saleId, $reason, $dispositions, $idempotencyKey, $expectedFingerprint): int {
            $previous = DB::table('stock_operations')->where('idempotency_key', $idempotencyKey)->first();
            if ($previous !== null) {
                $cancellation = DB::table('sale_cancellations')->where('stock_operation_id', $previous->id)->first();
                if ($cancellation === null || (int) $cancellation->sale_id !== $saleId || $previous->request_fingerprint !== $expectedFingerprint) {
                    throw new DomainException('La clave de confirmación ya pertenece a otra operación.');
                }

                return (int) $cancellation->id;
            }
            $sale = DB::table('sales')->where('id', $saleId)->where('status', 'confirmed')->lockForUpdate()->first();
            if ($sale === null) {
                throw new DomainException('Esta venta ya fue anulada.');
            }
            $preview = $this->preview($saleId, $reason, $dispositions);
            if ($preview['fingerprint'] !== $expectedFingerprint) {
                throw new DomainException('Cambió el saldo o el estado de la venta. Revisá la anulación nuevamente.');
            }
            $operationId = DB::table('stock_operations')->insertGetId([
                'idempotency_key' => $idempotencyKey, 'request_fingerprint' => $expectedFingerprint,
                'kind' => 'sale_cancellation', 'reason' => trim($reason), 'occurred_at' => now(), 'created_at' => now(),
            ]);
            $cancellationId = DB::table('sale_cancellations')->insertGetId([
                'sale_id' => $saleId, 'stock_operation_id' => $operationId, 'reason' => trim($reason),
                'debt_relieved_cents' => $preview['debt_relieved_cents'], 'refund_owed_cents' => $preview['refund_owed_cents'],
                'cancelled_at' => now(), 'created_at' => now(),
            ]);
            $items = DB::table('sale_items')->where('sale_id', $saleId)->orderBy('line_position')->get();
            foreach ($items as $item) {
                $entry = $dispositions[$item->id];
                $usable = $entry['usable'];
                DB::table('sale_cancellation_items')->insert([
                    'sale_cancellation_id' => $cancellationId, 'sale_item_id' => $item->id,
                    'quantity_usable' => $usable, 'quantity_unusable' => $item->quantity - $usable,
                    'unusable_reason' => $usable < $item->quantity ? trim($entry['reason']) : null,
                ]);
                if ($usable === 0) {
                    continue;
                }
                DB::table('stock_balances')->where('product_variant_id', $item->product_variant_id)->increment('quantity_on_hand', $usable, ['updated_at' => now()]);
                $quantityAfter = DB::table('stock_balances')->where('product_variant_id', $item->product_variant_id)->value('quantity_on_hand');
                $originalMovementId = DB::table('stock_movements')->where('stock_operation_id', $sale->stock_operation_id)->where('line_position', $item->line_position)->value('id');
                DB::table('stock_movements')->insert([
                    'stock_operation_id' => $operationId, 'line_position' => $item->line_position,
                    'product_variant_id' => $item->product_variant_id, 'quantity_delta' => $usable,
                    'quantity_after' => $quantityAfter, 'unit_cost_10000' => $item->unit_cost_snapshot_10000,
                    'reversal_of_movement_id' => $originalMovementId, 'created_at' => now(),
                ]);
            }
            DB::table('sales')->where('id', $saleId)->update(['status' => 'cancelled', 'updated_at' => now()]);

            return $cancellationId;
        });
    }
}
