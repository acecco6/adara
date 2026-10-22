<?php

namespace App\Actions\Sales;

use App\Services\Pricing\DecimalNumber;
use App\Services\Pricing\Money;
use App\Services\Pricing\SaleQuoteService;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateSaleAction
{
    public function __construct(private SaleQuoteService $quotes) {}

    /**
     * @param  array<array-key, mixed>  $lines
     * @param  array<array-key, mixed>  $payments
     * @return array{quote: array<string, mixed>, paid_cents: int, customer_name: ?string, payments: list<array<string, mixed>>, quote_fingerprint: string}
     */
    public function preview(array $lines, ?int $customerId, array $payments, string $discountType, string $discountInput): array
    {
        if (! array_is_list($lines) || $lines === [] || ! array_is_list($payments) || ($customerId !== null && $customerId <= 0)) {
            throw new InvalidArgumentException('La venta necesita renglones y clienta válidos.');
        }

        $normalizedLines = $this->normalizeLines($lines);
        $normalizedPayments = $this->normalizePayments($payments);
        [$discountType, $discountValue] = $this->quotes->normalizeDiscount($discountType, $discountInput);
        $customer = $customerId === null ? null : DB::table('customers')->where('id', $customerId)->where('is_active', true)->first();

        if ($customerId !== null && $customer === null) {
            throw new DomainException('La clienta no está disponible.');
        }

        $quote = $this->quotes->quote($normalizedLines, $discountType, $discountValue);
        $paid = 0;

        foreach ($normalizedPayments as $payment) {
            $paid = DecimalNumber::checkedAdd($paid, $payment['amount_cents']);
        }

        if ($paid > $quote['total'] || ($paid < $quote['total'] && $customer === null)) {
            throw new DomainException('Los cobros no pueden superar el total; el fiado requiere una clienta.');
        }

        return [
            'quote' => $quote, 'paid_cents' => $paid,
            'customer_name' => $customer?->name,
            'payments' => $normalizedPayments,
            'quote_fingerprint' => $this->quoteFingerprint($quote),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $lines
     * @param  array<array-key, mixed>  $payments
     */
    public function execute(array $lines, ?int $customerId, array $payments, string $discountType, string $discountInput, string $idempotencyKey, bool $acceptBelowCost = false, ?string $expectedQuoteFingerprint = null): int
    {
        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '' || Str::length($idempotencyKey) > 128 || ! array_is_list($lines) || $lines === []
            || ! array_is_list($payments) || ($customerId !== null && $customerId <= 0)) {
            throw new InvalidArgumentException('La venta necesita renglones, clienta y clave de confirmación válidos.');
        }

        $normalizedLines = $this->normalizeLines($lines);
        $normalizedPayments = $this->normalizePayments($payments);
        [$discountType, $discountValue] = $this->quotes->normalizeDiscount($discountType, $discountInput);
        $fingerprint = hash('sha256', json_encode([
            'sale', $normalizedLines, $customerId, $normalizedPayments, $discountType, $discountValue, $acceptBelowCost,
        ], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($normalizedLines, $customerId, $normalizedPayments, $discountType, $discountValue, $idempotencyKey, $fingerprint, $acceptBelowCost, $expectedQuoteFingerprint): int {
                $previous = DB::table('stock_operations')->where('idempotency_key', $idempotencyKey)->first();

                if ($previous !== null) {
                    return $this->previousSaleId((int) $previous->id, (string) $previous->request_fingerprint, $fingerprint);
                }

                $customer = $customerId === null ? null : DB::table('customers')->where('id', $customerId)->where('is_active', true)->first();

                if ($customerId !== null && $customer === null) {
                    throw new DomainException('La clienta no está disponible.');
                }

                $quote = $this->quotes->quote($normalizedLines, $discountType, $discountValue);

                if ($expectedQuoteFingerprint !== null && $expectedQuoteFingerprint !== $this->quoteFingerprint($quote)) {
                    throw new DomainException('El precio o el costo cambió desde la revisión. Revisá la venta nuevamente.');
                }

                $items = $quote['items'];
                $subtotal = $quote['subtotal'];
                $discount = $quote['discount'];
                $total = $quote['total'];
                $costTotal = $quote['cost_total'];
                $paid = 0;

                foreach ($normalizedPayments as $payment) {
                    $paid = DecimalNumber::checkedAdd($paid, $payment['amount_cents']);
                }

                if ($paid > $total || ($paid < $total && $customer === null)) {
                    throw new DomainException('Los cobros no pueden superar el total; el fiado requiere una clienta.');
                }

                $belowCost = $quote['below_cost'];

                if ($belowCost && ! $acceptBelowCost) {
                    throw new DomainException('La venta queda debajo del costo de reposición. Confirmá la pérdida estimada para continuar.');
                }

                $operationId = DB::table('stock_operations')->insertGetId([
                    'idempotency_key' => $idempotencyKey, 'request_fingerprint' => $fingerprint,
                    'kind' => 'sale', 'reason' => null, 'occurred_at' => now(), 'created_at' => now(),
                ]);
                $saleId = DB::table('sales')->insertGetId([
                    'stock_operation_id' => $operationId, 'customer_id' => $customerId, 'status' => 'confirmed',
                    'customer_name_snapshot' => $customer?->name, 'subtotal_cents' => $subtotal,
                    'discount_type' => $discountType, 'discount_value_decimal' => $discountValue,
                    'discount_base_cents' => $subtotal, 'discount_cents' => $discount, 'total_cents' => $total,
                    'cost_total_snapshot_10000' => $costTotal, 'cost_total_snapshot_cents' => $this->quotes->roundedCostCents($costTotal),
                    'initial_paid_cents' => $paid, 'initial_balance_cents' => $total - $paid,
                    'below_cost_accepted' => $belowCost,
                    'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('sales')->where('id', $saleId)->update(['number' => 'VEN-'.str_pad((string) $saleId, 6, '0', STR_PAD_LEFT)]);

                foreach ($items as $position => $item) {
                    $lineDiscount = $item['discount'];
                    DB::table('sale_items')->insert([
                        'sale_id' => $saleId, 'product_variant_id' => $item['variant_id'], 'line_position' => $position,
                        'quantity' => $item['quantity'], 'sku_snapshot' => $item['sku'],
                        'presentation_snapshot' => $item['presentation'], 'product_name_snapshot' => $item['product_name'],
                        'unit_price_snapshot_cents' => $item['price'], 'gross_cents' => $item['gross'],
                        'discount_base_per_unit_cents' => $item['discount_base_per_unit'],
                        'discount_cents' => $lineDiscount, 'net_cents' => $item['gross'] - $lineDiscount,
                        'unit_cost_snapshot_10000' => $item['cost'], 'cost_total_snapshot_10000' => $item['cost_total'],
                        'cost_total_snapshot_cents' => $this->quotes->roundedCostCents($item['cost_total']),
                    ]);

                    $updated = DB::table('stock_balances')->where('product_variant_id', $item['variant_id'])
                        ->where('quantity_on_hand', '>=', $item['quantity'])
                        ->decrement('quantity_on_hand', $item['quantity'], ['updated_at' => now()]);

                    if ($updated !== 1) {
                        throw new DomainException('El stock cambió durante la venta. Revisá el carrito.');
                    }

                    $quantityAfter = DB::table('stock_balances')->where('product_variant_id', $item['variant_id'])->value('quantity_on_hand');
                    DB::table('stock_movements')->insert([
                        'stock_operation_id' => $operationId, 'line_position' => $position,
                        'product_variant_id' => $item['variant_id'], 'quantity_delta' => -$item['quantity'],
                        'quantity_after' => $quantityAfter, 'unit_cost_10000' => $item['cost'], 'created_at' => now(),
                    ]);
                }

                foreach ($normalizedPayments as $position => $payment) {
                    $paymentId = DB::table('customer_payments')->insertGetId([
                        'origin_sale_id' => $saleId, 'customer_id' => $customerId,
                        'idempotency_key' => hash('sha256', $idempotencyKey.':'.$position),
                        'method' => $payment['method'], 'amount_cents' => $payment['amount_cents'],
                        'commission_cents' => $payment['commission_cents'],
                        'cash_tendered_cents' => $payment['cash_tendered_cents'],
                        'cash_change_cents' => $payment['cash_change_cents'],
                        'paid_at' => now(), 'created_at' => now(),
                    ]);
                    DB::table('payment_allocations')->insert([
                        'customer_payment_id' => $paymentId, 'sale_id' => $saleId, 'amount_cents' => $payment['amount_cents'],
                    ]);
                }

                return $saleId;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $previous = DB::table('stock_operations')->where('idempotency_key', $idempotencyKey)->first();

            if ($previous === null) {
                throw $exception;
            }

            return $this->previousSaleId((int) $previous->id, (string) $previous->request_fingerprint, $fingerprint);
        }
    }

    /** @param array<array-key, mixed> $lines
     * @return list<array{variant_id: int, quantity: int}>
     */
    private function normalizeLines(array $lines): array
    {
        $normalized = [];
        $seen = [];

        foreach ($lines as $line) {
            if (! is_array($line) || ! isset($line['variant_id'], $line['quantity'])
                || ! is_int($line['variant_id']) || $line['variant_id'] <= 0 || ! is_string($line['quantity'])) {
                throw new InvalidArgumentException('Cada renglón necesita presentación y cantidad válidas.');
            }

            $quantity = DecimalNumber::parseUnsigned(trim($line['quantity']), 0);

            if ($quantity === 0 || isset($seen[$line['variant_id']])) {
                throw new InvalidArgumentException('Las cantidades deben ser positivas y las presentaciones no deben repetirse.');
            }

            $seen[$line['variant_id']] = true;
            $normalized[] = ['variant_id' => $line['variant_id'], 'quantity' => $quantity];
        }

        return $normalized;
    }

    /** @param array<array-key, mixed> $payments
     * @return list<array{method: string, amount_cents: int, commission_cents: int, cash_tendered_cents: ?int, cash_change_cents: ?int}>
     */
    private function normalizePayments(array $payments): array
    {
        $normalized = [];

        foreach ($payments as $payment) {
            if (! is_array($payment) || ! isset($payment['method'], $payment['amount'], $payment['commission'])
                || ! is_string($payment['method']) || ! in_array($payment['method'], ['cash', 'transfer', 'mercado_pago', 'card'], true)
                || ! is_string($payment['amount']) || ! is_string($payment['commission'])) {
                throw new InvalidArgumentException('Cada cobro necesita medio, importe y comisión válidos.');
            }

            $amount = Money::fromDecimal(str_replace(',', '.', trim($payment['amount'])))->cents;
            $commission = Money::fromDecimal(str_replace(',', '.', trim($payment['commission'])))->cents;
            $tendered = null;
            $change = null;

            if ($amount === 0) {
                throw new InvalidArgumentException('El importe de un cobro debe ser mayor que cero.');
            }

            if ($payment['method'] === 'cash') {
                if (! isset($payment['cash_tendered']) || ! is_string($payment['cash_tendered'])) {
                    throw new InvalidArgumentException('Registrá el efectivo entregado por la clienta.');
                }

                $tendered = Money::fromDecimal(str_replace(',', '.', trim($payment['cash_tendered'])))->cents;

                if ($tendered < $amount || ($tendered > $amount && ($payment['change_confirmed'] ?? false) !== true)) {
                    throw new DomainException('El vuelto en efectivo debe calcularse y confirmarse.');
                }

                $change = $tendered - $amount;
            } elseif (array_key_exists('cash_tendered', $payment)) {
                throw new InvalidArgumentException('El efectivo entregado solo corresponde a cobros en efectivo.');
            }

            $normalized[] = [
                'method' => $payment['method'], 'amount_cents' => $amount,
                'commission_cents' => $commission, 'cash_tendered_cents' => $tendered,
                'cash_change_cents' => $change,
            ];
        }

        return $normalized;
    }

    private function previousSaleId(int $operationId, string $storedFingerprint, string $fingerprint): int
    {
        if ($storedFingerprint !== $fingerprint) {
            throw new InvalidArgumentException('La clave de confirmación ya se usó con otros datos.');
        }

        $saleId = DB::table('sales')->where('stock_operation_id', $operationId)->value('id');

        if ($saleId === null) {
            throw new DomainException('La clave ya pertenece a otra operación de stock.');
        }

        return (int) $saleId;
    }

    /** @param array<string, mixed> $quote */
    private function quoteFingerprint(array $quote): string
    {
        return hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR));
    }
}
