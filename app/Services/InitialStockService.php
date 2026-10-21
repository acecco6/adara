<?php

namespace App\Services;

use App\Services\Pricing\DecimalNumber;
use App\Services\Pricing\UnitCost;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InitialStockService
{
    public function record(int $variantId, string $quantityInput, string $costInput, string $idempotencyKey): int
    {
        $quantity = DecimalNumber::parseUnsigned(trim($quantityInput), 0);
        $cost = UnitCost::fromDecimal(str_replace(',', '.', trim($costInput)))->tenThousandths;
        $idempotencyKey = trim($idempotencyKey);

        if ($variantId <= 0 || $quantity === 0 || $cost === 0) {
            throw new InvalidArgumentException('Ingresá una cantidad y un costo real mayores que cero.');
        }

        if ($idempotencyKey === '' || Str::length($idempotencyKey) > 128) {
            throw new InvalidArgumentException('La clave de confirmación no es válida.');
        }

        $fingerprint = hash('sha256', json_encode(['initial_stock', $variantId, $quantity, $cost], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($variantId, $quantity, $cost, $idempotencyKey, $fingerprint): int {
                $previous = DB::table('stock_operations')->where('idempotency_key', $idempotencyKey)->first();

                if ($previous !== null) {
                    return $this->existingOperationId((int) $previous->id, (string) $previous->request_fingerprint, $fingerprint);
                }

                $variant = DB::table('product_variants')->where('id', $variantId)->first();
                $balance = DB::table('stock_balances')->where('product_variant_id', $variantId)->first();

                if ($variant === null || ! $variant->is_active || $variant->current_unit_cost_10000 !== null
                    || $variant->current_price_cents !== null || $balance === null || (int) $balance->quantity_on_hand !== 0
                    || DB::table('stock_movements')->where('product_variant_id', $variantId)->exists()) {
                    throw new DomainException('La carga inicial solo está disponible para una presentación activa, sin stock ni movimientos previos.');
                }

                $operationId = DB::table('stock_operations')->insertGetId([
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $fingerprint,
                    'kind' => 'initial_stock',
                    'reason' => 'Carga inicial de existencias',
                    'occurred_at' => now(),
                    'created_at' => now(),
                ]);

                $costUpdated = DB::table('product_variants')->where('id', $variantId)
                    ->where('is_active', true)->whereNull('current_unit_cost_10000')
                    ->update(['current_unit_cost_10000' => $cost, 'updated_at' => now()]);
                $balanceUpdated = DB::table('stock_balances')->where('product_variant_id', $variantId)
                    ->where('quantity_on_hand', 0)
                    ->update(['quantity_on_hand' => $quantity, 'updated_at' => now()]);

                if ($costUpdated !== 1 || $balanceUpdated !== 1) {
                    throw new DomainException('La presentación cambió durante la carga. Volvé a revisar el stock.');
                }

                DB::table('stock_movements')->insert([
                    'stock_operation_id' => $operationId,
                    'line_position' => 0,
                    'product_variant_id' => $variantId,
                    'quantity_delta' => $quantity,
                    'quantity_after' => $quantity,
                    'unit_cost_10000' => $cost,
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

    public function setMinimum(int $variantId, string $minimumInput): void
    {
        $minimum = DecimalNumber::parseUnsigned(trim($minimumInput), 0);

        if ($variantId <= 0 || DB::table('product_variants')->where('id', $variantId)->update([
            'minimum_stock' => $minimum,
            'updated_at' => now(),
        ]) !== 1) {
            throw new DomainException('La presentación no está disponible.');
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
