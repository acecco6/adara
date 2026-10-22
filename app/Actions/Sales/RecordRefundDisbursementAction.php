<?php

namespace App\Actions\Sales;

use App\Services\Pricing\Money;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RangeException;

class RecordRefundDisbursementAction
{
    private const DISPLAY_TIMEZONE = 'America/Argentina/Buenos_Aires';

    /** @return array{amount_cents: int, remaining_cents: int, paid_at: string, fingerprint: string} */
    public function preview(int $saleId, string $method, string $amount, string $paidAtLocal): array
    {
        [$amountCents, $paidAt] = $this->normalize($method, $amount, $paidAtLocal);
        $cancellation = DB::table('sale_cancellations')->where('sale_id', $saleId)->first();
        if ($cancellation === null) {
            throw new DomainException('Esta venta no tiene un reintegro pendiente.');
        }
        $cancelledAt = Carbon::parse($cancellation->cancelled_at, 'UTC');
        if ($paidAt->lt($cancelledAt) && $paidAt->format('Y-m-d H:i') === $cancelledAt->format('Y-m-d H:i')) {
            $paidAt = $cancelledAt;
        }
        if ($paidAt->lt($cancelledAt) || $paidAt->gt(now())) {
            throw new InvalidArgumentException('La fecha debe estar entre la anulación y el momento actual.');
        }
        $alreadyPaid = (int) DB::table('refund_disbursements')->where('sale_cancellation_id', $cancellation->id)->sum('amount_cents');
        $remaining = (int) $cancellation->refund_owed_cents - $alreadyPaid;
        if ($remaining <= 0 || $amountCents > $remaining) {
            throw new DomainException('El reintegro supera el importe pendiente de esta venta.');
        }

        return [
            'amount_cents' => $amountCents,
            'remaining_cents' => $remaining - $amountCents,
            'paid_at' => $paidAt->toDateTimeString(),
            'fingerprint' => hash('sha256', json_encode([$saleId, $method, $amountCents, $paidAt->toDateTimeString(), $alreadyPaid], JSON_THROW_ON_ERROR)),
        ];
    }

    public function execute(int $saleId, string $method, string $amount, string $paidAtLocal, string $idempotencyKey, string $expectedFingerprint): int
    {
        if (trim($idempotencyKey) === '' || Str::length($idempotencyKey) > 128) {
            throw new InvalidArgumentException('La clave de confirmación no es válida.');
        }

        return DB::transaction(function () use ($saleId, $method, $amount, $paidAtLocal, $idempotencyKey, $expectedFingerprint): int {
            $previous = DB::table('refund_disbursements')->where('idempotency_key', $idempotencyKey)->first();
            if ($previous !== null) {
                [$amountCents, $paidAt] = $this->normalize($method, $amount, $paidAtLocal);
                $cancellationSaleId = DB::table('sale_cancellations')->where('id', $previous->sale_cancellation_id)->value('sale_id');
                if ((int) $cancellationSaleId !== $saleId || $previous->method !== $method || (int) $previous->amount_cents !== $amountCents || $previous->request_fingerprint !== $expectedFingerprint || substr($previous->paid_at, 0, 16) !== $paidAt->format('Y-m-d H:i')) {
                    throw new DomainException('La clave de confirmación ya se usó para otro reintegro.');
                }

                return (int) $previous->id;
            }
            $cancellation = DB::table('sale_cancellations')->where('sale_id', $saleId)->lockForUpdate()->first();
            if ($cancellation === null) {
                throw new DomainException('Esta venta no tiene un reintegro pendiente.');
            }
            $review = $this->preview($saleId, $method, $amount, $paidAtLocal);
            if ($review['fingerprint'] !== $expectedFingerprint) {
                throw new DomainException('El saldo cambió desde la revisión. Revisá el reintegro nuevamente.');
            }

            return DB::table('refund_disbursements')->insertGetId([
                'sale_cancellation_id' => $cancellation->id,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $expectedFingerprint,
                'method' => $method,
                'amount_cents' => $review['amount_cents'],
                'paid_at' => $review['paid_at'],
                'created_at' => now(),
            ]);
        });
    }

    /** @return array{int, Carbon} */
    private function normalize(string $method, string $amount, string $paidAtLocal): array
    {
        if (! in_array($method, ['cash', 'transfer', 'mercado_pago', 'card'], true)) {
            throw new InvalidArgumentException('Elegí un medio de reintegro válido.');
        }
        try {
            $amountCents = Money::fromDecimal(str_replace(',', '.', trim($amount)))->cents;
        } catch (RangeException|InvalidArgumentException) {
            throw new InvalidArgumentException('Ingresá un importe en pesos válido, por ejemplo 9000,00.');
        }
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('El reintegro debe ser mayor que cero.');
        }
        $local = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $paidAtLocal, new DateTimeZone(self::DISPLAY_TIMEZONE));
        if ($local === false || $local->format('Y-m-d\TH:i') !== $paidAtLocal) {
            throw new InvalidArgumentException('Ingresá una fecha y hora válidas para el reintegro.');
        }

        return [$amountCents, Carbon::instance($local)->setTimezone('UTC')];
    }
}
