<?php

namespace App\Services;

use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StockAdjustmentService
{
    public function adjust(int $variantId, int $delta, string $reason, string $idempotencyKey): int
    {
        $reason = trim($reason);
        $idempotencyKey = trim($idempotencyKey);

        if ($variantId <= 0 || $delta === 0 || $delta === PHP_INT_MIN) {
            throw new InvalidArgumentException('La presentación y la cantidad del ajuste deben ser válidas.');
        }

        if ($reason === '' || Str::length($reason) > 1000) {
            throw new InvalidArgumentException('El ajuste requiere un motivo de hasta 1000 caracteres.');
        }

        if ($idempotencyKey === '' || Str::length($idempotencyKey) > 128) {
            throw new InvalidArgumentException('La clave de confirmación no es válida.');
        }

        $fingerprint = hash('sha256', json_encode(
            ['manual_adjustment', $variantId, $delta, $reason],
            JSON_THROW_ON_ERROR,
        ));

        try {
            return DB::transaction(function () use ($variantId, $delta, $reason, $idempotencyKey, $fingerprint): int {
                $previous = DB::table('stock_operations')->where('idempotency_key', $idempotencyKey)->first();

                if ($previous !== null) {
                    return $this->existingOperationId((int) $previous->id, (string) $previous->request_fingerprint, $fingerprint);
                }

                $operationId = DB::table('stock_operations')->insertGetId([
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $fingerprint,
                    'kind' => 'manual_adjustment',
                    'reason' => $reason,
                    'occurred_at' => now(),
                    'created_at' => now(),
                ]);

                $balance = DB::table('stock_balances')->where('product_variant_id', $variantId);
                $updated = $delta < 0
                    ? $balance->where('quantity_on_hand', '>=', -$delta)
                        ->decrement('quantity_on_hand', -$delta, ['updated_at' => now()])
                    : $balance->where('quantity_on_hand', '<=', PHP_INT_MAX - $delta)
                        ->increment('quantity_on_hand', $delta, ['updated_at' => now()]);

                if ($updated !== 1) {
                    throw new DomainException('Stock insuficiente, presentación inexistente o cantidad fuera de rango.');
                }

                $quantityAfter = DB::table('stock_balances')
                    ->where('product_variant_id', $variantId)
                    ->value('quantity_on_hand');

                DB::table('stock_movements')->insert([
                    'stock_operation_id' => $operationId,
                    'line_position' => 0,
                    'product_variant_id' => $variantId,
                    'quantity_delta' => $delta,
                    'quantity_after' => $quantityAfter,
                    'unit_cost_10000' => null,
                    'created_at' => now(),
                ]);

                return $operationId;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $previous = DB::table('stock_operations')->where('idempotency_key', $idempotencyKey)->first();

            if ($previous === null) {
                throw $exception;
            }

            return $this->existingOperationId((int) $previous->id, (string) $previous->request_fingerprint, $fingerprint);
        }
    }

    private function existingOperationId(int $operationId, string $storedFingerprint, string $fingerprint): int
    {
        if ($storedFingerprint !== $fingerprint) {
            throw new InvalidArgumentException('La clave de confirmación ya se usó con otros datos.');
        }

        return $operationId;
    }
}
