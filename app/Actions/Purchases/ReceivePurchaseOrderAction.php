<?php

namespace App\Actions\Purchases;

use App\Services\Pricing\AllocateShippingService;
use App\Services\Pricing\DecimalNumber;
use App\Services\Pricing\InventoryCostService;
use App\Services\Pricing\Money;
use App\Services\Pricing\Percentage;
use App\Services\Pricing\PriceCalculator;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReceivePurchaseOrderAction
{
    public function __construct(
        private AllocateShippingService $shipping,
        private InventoryCostService $costs,
        private PriceCalculator $prices,
    ) {}

    /**
     * @param  array<array-key, mixed>  $lines  Untrusted form data in the order shown on the receipt.
     */
    public function execute(
        int $orderId,
        array $lines,
        string $additionalShippingInput,
        string $idempotencyKey,
        bool $acceptCostChange = false,
        bool $acceptOverdelivery = false,
    ): int {
        $idempotencyKey = trim($idempotencyKey);

        if ($orderId <= 0 || $idempotencyKey === '' || Str::length($idempotencyKey) > 128 || ! array_is_list($lines) || $lines === []) {
            throw new InvalidArgumentException('La recepción necesita un pedido, líneas ordenadas y una clave de confirmación válidos.');
        }

        $additionalShipping = Money::fromDecimal(str_replace(',', '.', trim($additionalShippingInput)));
        $received = [];

        foreach ($lines as $line) {
            if (! is_array($line) || ! isset($line['order_line_id'], $line['quantity'], $line['unit_paid'])
                || ! is_int($line['order_line_id']) || $line['order_line_id'] <= 0
                || ! is_string($line['quantity']) || ! is_string($line['unit_paid'])) {
                throw new InvalidArgumentException('Cada línea necesita identificación, cantidad y costo pagado.');
            }

            $quantity = DecimalNumber::parseUnsigned(trim($line['quantity']), 0);
            $unitPaid = Money::fromDecimal(str_replace(',', '.', trim($line['unit_paid'])))->cents;

            if ($quantity === 0 || $unitPaid === 0) {
                throw new InvalidArgumentException('La cantidad y el costo pagado deben ser mayores que cero.');
            }

            DecimalNumber::checkedMultiply($quantity, $unitPaid);
            $received[] = ['order_line_id' => $line['order_line_id'], 'quantity' => $quantity, 'unit_paid_cents' => $unitPaid];
        }

        $fingerprint = hash('sha256', json_encode(['purchase_receipt', $orderId, $received, $additionalShipping->cents, $acceptCostChange, $acceptOverdelivery], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($orderId, $received, $additionalShipping, $idempotencyKey, $fingerprint, $acceptCostChange, $acceptOverdelivery): int {
                $previous = DB::table('stock_operations')->where('idempotency_key', $idempotencyKey)->first();

                if ($previous !== null) {
                    return $this->previousReceiptId((int) $previous->id, (string) $previous->request_fingerprint, $fingerprint);
                }

                $order = DB::table('purchase_orders')->where('id', $orderId)->first();

                if ($order === null || ! in_array($order->status, ['confirmed', 'partially_received'], true)) {
                    throw new DomainException('El pedido debe estar confirmado y conservar cantidades pendientes.');
                }

                $orderLines = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->orderBy('line_position')->get();

                $globalMarkup = DB::table('pricing_settings')->where('id', 1)->value('global_markup_decimal');
                $variants = [];
                $hasCostChange = false;
                $receivedBefore = DB::table('purchase_receipt_lines')
                    ->join('purchase_receipts', 'purchase_receipts.id', '=', 'purchase_receipt_lines.purchase_receipt_id')
                    ->where('purchase_receipts.purchase_order_id', $orderId)
                    ->select('purchase_receipt_lines.purchase_order_line_id')
                    ->selectRaw('SUM(purchase_receipt_lines.quantity_received) as quantity')
                    ->groupBy('purchase_receipt_lines.purchase_order_line_id')
                    ->get()->keyBy('purchase_order_line_id');
                $extendedBefore = DB::table('purchase_order_quantity_extensions')
                    ->join('purchase_receipts', 'purchase_receipts.id', '=', 'purchase_order_quantity_extensions.purchase_receipt_id')
                    ->where('purchase_receipts.purchase_order_id', $orderId)
                    ->select('purchase_order_quantity_extensions.purchase_order_line_id')
                    ->selectRaw('SUM(purchase_order_quantity_extensions.extra_quantity) as quantity')
                    ->groupBy('purchase_order_quantity_extensions.purchase_order_line_id')
                    ->get()->keyBy('purchase_order_line_id');
                $appliedBefore = (int) DB::table('purchase_receipts')->where('purchase_order_id', $orderId)->sum('general_shipping_applied_cents');
                $remainingShipping = (int) $order->general_shipping_cents - $appliedBefore;

                if ($remainingShipping < 0) {
                    throw new DomainException('El envío aplicado supera el importe general del pedido.');
                }

                $inputsByOrderLine = [];
                $extras = [];
                $lastPosition = -1;

                foreach ($received as $position => $input) {
                    $orderLine = $orderLines->firstWhere('id', $input['order_line_id']);

                    if ($orderLine === null || $orderLine->line_position <= $lastPosition) {
                        throw new DomainException('Las líneas recibidas deben pertenecer al pedido y conservar su orden.');
                    }

                    $lastPosition = (int) $orderLine->line_position;
                    $lineId = (int) $orderLine->id;
                    $alreadyReceived = $receivedBefore->has($lineId) ? (int) $receivedBefore->get($lineId)->quantity : 0;
                    $extended = $extendedBefore->has($lineId) ? (int) $extendedBefore->get($lineId)->quantity : 0;
                    $authorized = DecimalNumber::checkedAdd((int) $orderLine->quantity_ordered, $extended);
                    $remaining = $authorized - $alreadyReceived;

                    if ($remaining < 0) {
                        throw new DomainException('El historial recibido supera la cantidad autorizada.');
                    }

                    $extra = max(0, $input['quantity'] - $remaining);

                    if ($extra > 0 && ! $acceptOverdelivery) {
                        throw new DomainException('La sobreentrega requiere una confirmación explícita.');
                    }

                    $extras[$lineId] = $extra;
                    $inputsByOrderLine[$lineId] = ['input' => $input, 'position' => $position];

                    if ((int) $orderLine->unit_paid_cents !== $input['unit_paid_cents']) {
                        if (! $acceptCostChange) {
                            throw new DomainException('El costo recibido difiere del pedido y requiere confirmación explícita.');
                        }

                        $hasCostChange = true;
                    }

                    $variantId = (int) $orderLine->product_variant_id;

                    if (! isset($variants[$variantId])) {
                        $variant = DB::table('product_variants')->where('id', $variantId)->first();

                        if ($variant === null || ! $variant->is_active) {
                            throw new DomainException('La presentación debe estar activa para recibir mercadería.');
                        }

                        $markup = $variant->markup_override_decimal ?? $globalMarkup;

                        if ($markup === null) {
                            throw new DomainException('Definí el recargo antes de recibir mercadería.');
                        }

                        $markup = str_replace(',', '.', (string) $markup);
                        Percentage::fromDecimal($markup);
                        $variants[$variantId] = ['quantity' => 0, 'merchandise' => 0, 'shipping' => 0, 'markup' => $markup];
                    }

                }

                $futureWeights = [];
                $chunkPositions = [];
                $pendingAfter = 0;

                foreach ($orderLines as $orderLine) {
                    $lineId = (int) $orderLine->id;
                    $extended = $extendedBefore->has($lineId) ? (int) $extendedBefore->get($lineId)->quantity : 0;
                    $alreadyReceived = $receivedBefore->has($lineId) ? (int) $receivedBefore->get($lineId)->quantity : 0;
                    $authorized = DecimalNumber::checkedAdd((int) $orderLine->quantity_ordered, $extended);
                    $remaining = $authorized - $alreadyReceived;
                    $current = $inputsByOrderLine[$lineId] ?? null;

                    if ($current !== null) {
                        $futureWeights[] = [
                            'quantity' => $current['input']['quantity'],
                            'unit_paid' => Money::fromCents($current['input']['unit_paid_cents']),
                        ];
                        $chunkPositions[] = $current['position'];
                    }

                    $futureQuantity = max(0, $remaining - ($current['input']['quantity'] ?? 0));
                    $pendingAfter = DecimalNumber::checkedAdd($pendingAfter, $futureQuantity);

                    if ($futureQuantity > 0) {
                        $futureWeights[] = ['quantity' => $futureQuantity, 'unit_paid' => Money::fromCents((int) $orderLine->unit_paid_cents)];
                        $chunkPositions[] = null;
                    }
                }

                $chunkShares = $this->shipping->allocate(Money::fromCents($remainingShipping), (string) $order->shipping_method, $futureWeights);
                $generalShares = array_fill(0, count($received), Money::fromCents(0));

                foreach ($chunkPositions as $chunk => $receivedPosition) {
                    if ($receivedPosition !== null) {
                        $generalShares[$receivedPosition] = $chunkShares[$chunk];
                    }
                }

                $additionalShares = $this->shipping->allocate($additionalShipping, (string) $order->shipping_method, array_map(
                    fn (array $input): array => ['quantity' => $input['quantity'], 'unit_paid' => Money::fromCents($input['unit_paid_cents'])],
                    $received,
                ));
                $generalApplied = array_reduce($generalShares, fn (int $total, Money $share): int => DecimalNumber::checkedAdd($total, $share->cents), 0);

                foreach ($received as $position => $input) {
                    $orderLine = $orderLines->firstWhere('id', $input['order_line_id']);
                    $variantId = (int) $orderLine->product_variant_id;
                    $merchandise = DecimalNumber::checkedMultiply($input['quantity'], $input['unit_paid_cents']);
                    $totals = $variants[$variantId];
                    $variants[$variantId] = [
                        'quantity' => DecimalNumber::checkedAdd($totals['quantity'], $input['quantity']),
                        'merchandise' => DecimalNumber::checkedAdd($totals['merchandise'], $merchandise),
                        'shipping' => DecimalNumber::checkedAdd(
                            $totals['shipping'],
                            DecimalNumber::checkedAdd($generalShares[$position]->cents, $additionalShares[$position]->cents),
                        ),
                        'markup' => $totals['markup'],
                    ];
                }

                $valuedVariants = [];

                foreach ($variants as $variantId => $totals) {
                    $cost = $this->costs->unitCostForReceipt(
                        Money::fromCents($totals['merchandise']),
                        Money::fromCents($totals['shipping']),
                        $totals['quantity'],
                    );
                    $valuedVariants[$variantId] = [
                        'cost' => $cost->tenThousandths,
                        'price' => $this->prices->catalogPrice($cost, Percentage::fromDecimal($totals['markup']))->cents,
                        'markup' => $totals['markup'],
                    ];
                }

                $operationId = DB::table('stock_operations')->insertGetId([
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $fingerprint,
                    'kind' => 'purchase_receipt',
                    'reason' => null,
                    'occurred_at' => now(),
                    'created_at' => now(),
                ]);
                $receiptId = DB::table('purchase_receipts')->insertGetId([
                    'purchase_order_id' => $orderId,
                    'stock_operation_id' => $operationId,
                    'general_shipping_applied_cents' => $generalApplied,
                    'additional_shipping_cents' => $additionalShipping->cents,
                    'cost_change_accepted' => $hasCostChange,
                    'received_at' => now(),
                    'created_at' => now(),
                ]);

                foreach ($received as $position => $input) {
                    $orderLine = $orderLines->firstWhere('id', $input['order_line_id']);
                    $variantId = (int) $orderLine->product_variant_id;
                    $totals = $valuedVariants[$variantId];
                    $quantity = $input['quantity'];

                    if ($extras[(int) $orderLine->id] > 0) {
                        DB::table('purchase_order_quantity_extensions')->insert([
                            'purchase_receipt_id' => $receiptId,
                            'purchase_order_line_id' => $orderLine->id,
                            'extra_quantity' => $extras[(int) $orderLine->id],
                            'created_at' => now(),
                        ]);
                    }

                    DB::table('purchase_receipt_lines')->insert([
                        'purchase_receipt_id' => $receiptId,
                        'purchase_order_line_id' => $orderLine->id,
                        'product_variant_id' => $variantId,
                        'line_position' => $position,
                        'quantity_received' => $quantity,
                        'unit_paid_cents' => $input['unit_paid_cents'],
                        'merchandise_paid_cents' => DecimalNumber::checkedMultiply($quantity, $input['unit_paid_cents']),
                        'general_shipping_applied_cents' => $generalShares[$position]->cents,
                        'additional_shipping_applied_cents' => $additionalShares[$position]->cents,
                        'unit_cost_10000' => $totals['cost'],
                        'price_cents' => $totals['price'],
                        'markup_decimal' => $totals['markup'],
                    ]);

                    $updated = DB::table('stock_balances')->where('product_variant_id', $variantId)
                        ->where('quantity_on_hand', '<=', PHP_INT_MAX - $quantity)
                        ->increment('quantity_on_hand', $quantity, ['updated_at' => now()]);

                    if ($updated !== 1) {
                        throw new DomainException('No se pudo actualizar el stock de la presentación.');
                    }

                    DB::table('stock_movements')->insert([
                        'stock_operation_id' => $operationId,
                        'line_position' => $position,
                        'product_variant_id' => $variantId,
                        'quantity_delta' => $quantity,
                        'quantity_after' => DB::table('stock_balances')->where('product_variant_id', $variantId)->value('quantity_on_hand'),
                        'unit_cost_10000' => $totals['cost'],
                        'created_at' => now(),
                    ]);
                }

                foreach ($valuedVariants as $variantId => $totals) {
                    DB::table('product_variants')->where('id', $variantId)->update([
                        'current_unit_cost_10000' => $totals['cost'],
                        'current_price_cents' => $totals['price'],
                        'updated_at' => now(),
                    ]);
                }

                DB::table('purchase_orders')->where('id', $orderId)->update([
                    'status' => $pendingAfter === 0 ? 'received' : 'partially_received',
                    'updated_at' => now(),
                ]);

                return $receiptId;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $previous = DB::table('stock_operations')->where('idempotency_key', $idempotencyKey)->first();

            if ($previous === null) {
                throw $exception;
            }

            return $this->previousReceiptId((int) $previous->id, (string) $previous->request_fingerprint, $fingerprint);
        }
    }

    private function previousReceiptId(int $operationId, string $storedFingerprint, string $fingerprint): int
    {
        if ($storedFingerprint !== $fingerprint) {
            throw new InvalidArgumentException('La clave de confirmación ya se usó con otros datos.');
        }

        $receiptId = DB::table('purchase_receipts')->where('stock_operation_id', $operationId)->value('id');

        if ($receiptId === null) {
            throw new InvalidArgumentException('La clave de confirmación pertenece a otra operación.');
        }

        return (int) $receiptId;
    }
}
