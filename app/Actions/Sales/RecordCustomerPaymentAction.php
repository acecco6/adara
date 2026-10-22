<?php

namespace App\Actions\Sales;

use App\Services\Pricing\Money;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RangeException;
use stdClass;

class RecordCustomerPaymentAction
{
    /**
     * @return array{customer_id: int, amount_cents: int, commission_cents: int, cash_tendered_cents: ?int, cash_change_cents: ?int, method: string, allocations: list<array{sale_id: int, number: string, amount_cents: int}>, fingerprint: string}
     */
    public function preview(int $saleId, string $method, string $amount, string $commission, ?string $cashTendered, bool $changeConfirmed): array
    {
        if (! in_array($method, ['cash', 'transfer', 'mercado_pago', 'card'], true)) {
            throw new InvalidArgumentException('Elegí un medio de cobro válido.');
        }

        $sale = DB::table('sales')->where('id', $saleId)->where('status', 'confirmed')->first();

        if ($sale === null || $sale->customer_id === null) {
            throw new DomainException('Esta venta no tiene una clienta con deuda cobrable.');
        }

        $selectedCollected = (int) DB::table('payment_allocations')->where('sale_id', $saleId)->sum('amount_cents');

        if ($selectedCollected >= $sale->total_cents) {
            throw new DomainException('Esta venta ya está cobrada.');
        }

        $amountCents = $this->parseAmount($amount, 'collectionAmount', 'importe a cobrar');
        $commissionCents = $this->parseAmount($commission, 'collectionCommission', 'comisión');

        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Ingresá un importe mayor que cero.');
        }

        $tenderedCents = null;
        $changeCents = null;

        if ($method === 'cash') {
            $tenderedCents = $this->parseAmount($cashTendered ?? '', 'collectionTendered', 'efectivo recibido');

            if ($tenderedCents < $amountCents) {
                throw new DomainException('collectionTendered: El efectivo recibido no puede ser menor que el importe a cobrar.');
            }

            if ($tenderedCents > $amountCents && ! $changeConfirmed) {
                throw new DomainException('collectionTendered: Confirmá el vuelto antes de registrar el cobro.');
            }

            $changeCents = $tenderedCents - $amountCents;
        }

        if ($amountCents > (int) $sale->total_cents - $selectedCollected) {
            throw new DomainException('El cobro supera el saldo pendiente de esta venta.');
        }

        $allocations = [['sale_id' => $saleId, 'number' => (string) $sale->number, 'amount_cents' => $amountCents]];

        $fingerprint = hash('sha256', json_encode([$saleId, $method, $amountCents, $commissionCents, $tenderedCents, $changeCents, $allocations], JSON_THROW_ON_ERROR));

        return [
            'customer_id' => (int) $sale->customer_id, 'amount_cents' => $amountCents,
            'commission_cents' => $commissionCents, 'cash_tendered_cents' => $tenderedCents,
            'cash_change_cents' => $changeCents, 'method' => $method, 'allocations' => $allocations,
            'fingerprint' => $fingerprint,
        ];
    }

    public function execute(int $saleId, string $method, string $amount, string $commission, ?string $cashTendered, bool $changeConfirmed, string $idempotencyKey, string $expectedFingerprint): int
    {
        if (trim($idempotencyKey) === '' || Str::length($idempotencyKey) > 128) {
            throw new InvalidArgumentException('La clave de confirmación no es válida.');
        }

        try {
            return DB::transaction(function () use ($saleId, $method, $amount, $commission, $cashTendered, $changeConfirmed, $idempotencyKey, $expectedFingerprint): int {
                $previous = DB::table('customer_payments')->where('idempotency_key', $idempotencyKey)->first();

                if ($previous !== null) {
                    return $this->previousPaymentId($previous, $saleId, $method, $amount, $commission, $cashTendered);
                }

                $preview = $this->preview($saleId, $method, $amount, $commission, $cashTendered, $changeConfirmed);

                if ($preview['fingerprint'] !== $expectedFingerprint) {
                    throw new DomainException('La deuda cambió desde la revisión. Revisá el cobro otra vez.');
                }

                $paymentId = DB::table('customer_payments')->insertGetId([
                    'origin_sale_id' => $saleId, 'customer_id' => $preview['customer_id'],
                    'idempotency_key' => $idempotencyKey, 'method' => $method,
                    'amount_cents' => $preview['amount_cents'], 'commission_cents' => $preview['commission_cents'],
                    'cash_tendered_cents' => $preview['cash_tendered_cents'], 'cash_change_cents' => $preview['cash_change_cents'],
                    'paid_at' => now(), 'created_at' => now(),
                ]);

                foreach ($preview['allocations'] as $allocation) {
                    DB::table('payment_allocations')->insert([
                        'customer_payment_id' => $paymentId, 'sale_id' => $allocation['sale_id'],
                        'amount_cents' => $allocation['amount_cents'],
                    ]);
                }

                return $paymentId;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $previous = DB::table('customer_payments')->where('idempotency_key', $idempotencyKey)->first();

            if ($previous === null) {
                throw $exception;
            }

            return $this->previousPaymentId($previous, $saleId, $method, $amount, $commission, $cashTendered);
        }
    }

    private function previousPaymentId(stdClass $payment, int $saleId, string $method, string $amount, string $commission, ?string $cashTendered): int
    {
        if ((int) $payment->origin_sale_id !== $saleId || $payment->method !== $method
            || (int) $payment->amount_cents !== Money::fromDecimal(str_replace(',', '.', trim($amount)))->cents
            || (int) $payment->commission_cents !== Money::fromDecimal(str_replace(',', '.', trim($commission)))->cents
            || ($method === 'cash' && (int) $payment->cash_tendered_cents !== Money::fromDecimal(str_replace(',', '.', trim($cashTendered ?? '')))->cents)) {
            throw new DomainException('La clave de confirmación ya se usó con otros datos.');
        }

        return (int) $payment->id;
    }

    private function parseAmount(string $value, string $field, string $label): int
    {
        try {
            return Money::fromDecimal(str_replace(',', '.', trim($value)))->cents;
        } catch (InvalidArgumentException|RangeException) {
            throw new InvalidArgumentException($field.': Ingresá un '.$label.' válido, por ejemplo 50000,00.');
        }
    }
}
