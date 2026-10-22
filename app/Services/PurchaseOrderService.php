<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Services\Pricing\AllocateShippingService;
use App\Services\Pricing\DecimalNumber;
use App\Services\Pricing\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RangeException;

class PurchaseOrderService
{
    public function __construct(private AllocateShippingService $shipping) {}

    /** @param array<string, mixed> $input */
    public function createDraft(array $input): PurchaseOrder
    {
        $data = $this->validatedDraft($input);

        return DB::transaction(function () use ($data): PurchaseOrder {
            $order = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'status' => 'draft',
                'shipping_method' => $data['shipping_method'],
                'general_shipping_cents' => $data['general_shipping_cents'],
                'notes' => $data['notes'],
            ]);
            $order->forceFill(['number' => 'PED-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)])->save();
            $this->replaceDraftLines($order, $data['lines']);

            return $order->load('lines');
        });
    }

    /** @param array<string, mixed> $input */
    public function updateDraft(int $orderId, array $input): PurchaseOrder
    {
        $data = $this->validatedDraft($input);

        return DB::transaction(function () use ($orderId, $data): PurchaseOrder {
            $order = PurchaseOrder::findOrFail($orderId);

            if ($order->status !== 'draft') {
                throw new DomainException('Solo se puede editar un pedido en borrador.');
            }

            $order->update([
                'supplier_id' => $data['supplier_id'],
                'shipping_method' => $data['shipping_method'],
                'general_shipping_cents' => $data['general_shipping_cents'],
                'notes' => $data['notes'],
            ]);
            $order->lines()->delete();
            $this->replaceDraftLines($order, $data['lines']);

            return $order->load('lines');
        });
    }

    public function confirm(int $orderId): PurchaseOrder
    {
        return DB::transaction(function () use ($orderId): PurchaseOrder {
            $order = PurchaseOrder::with(['supplier', 'lines.variant.group'])->findOrFail($orderId);

            if ($order->status === 'confirmed') {
                return $order;
            }

            if ($order->status !== 'draft') {
                throw new DomainException('El pedido ya no está en borrador.');
            }

            if (! $order->supplier->is_active || $order->lines->isEmpty()) {
                throw new DomainException('El pedido necesita un proveedor activo y al menos una presentación.');
            }

            $weights = [];

            foreach ($order->lines as $line) {
                if (! $line->variant->is_active || $line->unit_paid_cents === 0) {
                    throw new DomainException('Cada presentación debe estar activa y tener un costo neto pagado mayor que cero.');
                }

                $weights[] = [
                    'quantity' => $line->quantity_ordered,
                    'unit_paid' => Money::fromCents($line->unit_paid_cents),
                ];
            }

            $this->shipping->allocate(
                Money::fromCents($order->general_shipping_cents),
                $order->shipping_method,
                $weights,
            );

            foreach ($order->lines as $line) {
                $line->forceFill([
                    'sku_snapshot' => $line->variant->sku,
                    'presentation_snapshot' => $line->variant->name,
                    'product_name_snapshot' => $line->variant->group->name,
                ])->save();
            }

            $order->forceFill([
                'supplier_name_snapshot' => $order->supplier->name,
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ])->save();

            return $order->load('lines');
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{supplier_id: int, shipping_method: string, general_shipping_cents: int, notes: ?string, lines: list<array{product_variant_id: int, quantity_ordered: int, unit_paid_cents: int}>}
     */
    private function validatedDraft(array $input): array
    {
        $validated = Validator::make($input + ['general_shipping' => '0', 'lines' => []], [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'shipping_method' => ['required', Rule::in(['quantity', 'merchandise_cost'])],
            'general_shipping' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]*)(?:[.,][0-9]{1,2})?\z/D'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['array'],
            'lines.*' => ['array:variant_id,quantity,unit_paid'],
            'lines.*.variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where('is_active', true)],
            'lines.*.quantity' => ['required', 'regex:/\A[1-9][0-9]*\z/D'],
            'lines.*.unit_paid' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]*)(?:[.,][0-9]{1,2})?\z/D'],
        ], [
            'supplier_id.exists' => 'Seleccioná un proveedor activo.',
            'shipping_method.in' => 'Elegí un método de distribución válido.',
            'general_shipping.regex' => 'Ingresá el envío en pesos, con hasta dos decimales.',
            'lines.*.variant_id.exists' => 'Seleccioná presentaciones activas.',
            'lines.*.quantity.regex' => 'La cantidad debe ser un entero positivo.',
            'lines.*.unit_paid.regex' => 'Ingresá un costo unitario en pesos, con hasta dos decimales.',
        ])->validate();

        $rawLines = $validated['lines'] ?? [];

        if (! array_is_list($rawLines)) {
            throw ValidationException::withMessages(['lines' => 'Las líneas deben conservar su orden.']);
        }

        try {
            $generalShipping = Money::fromDecimal(str_replace(',', '.', trim($validated['general_shipping'])))->cents;
        } catch (InvalidArgumentException|RangeException) {
            throw ValidationException::withMessages(['general_shipping' => 'El importe de envío está fuera de rango.']);
        }

        $lines = [];

        foreach ($rawLines as $position => $rawLine) {
            try {
                $quantity = DecimalNumber::parseUnsigned((string) $rawLine['quantity'], 0);
                $unitPaid = Money::fromDecimal(str_replace(',', '.', trim($rawLine['unit_paid'])))->cents;
                DecimalNumber::checkedMultiply($quantity, $unitPaid);
            } catch (InvalidArgumentException|RangeException) {
                throw ValidationException::withMessages(['lines.'.$position => 'La cantidad o el costo de esta línea está fuera de rango.']);
            }

            $lines[] = [
                'product_variant_id' => (int) $rawLine['variant_id'],
                'quantity_ordered' => $quantity,
                'unit_paid_cents' => $unitPaid,
            ];
        }

        return [
            'supplier_id' => (int) $validated['supplier_id'],
            'shipping_method' => (string) $validated['shipping_method'],
            'general_shipping_cents' => $generalShipping,
            'notes' => isset($validated['notes']) && trim($validated['notes']) !== '' ? trim($validated['notes']) : null,
            'lines' => $lines,
        ];
    }

    /** @param list<array{product_variant_id: int, quantity_ordered: int, unit_paid_cents: int}> $lines */
    private function replaceDraftLines(PurchaseOrder $order, array $lines): void
    {
        foreach ($lines as $position => $line) {
            $order->lines()->create($line + ['line_position' => $position]);
        }
    }
}
