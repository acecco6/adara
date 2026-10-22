<?php

namespace App\Services\Pricing;

use InvalidArgumentException;

class AllocateShippingService
{
    /**
     * Lines must follow their persisted order. Any remaining cents go to the last eligible unit.
     *
     * @param  array<array-key, mixed>  $lines  Ordered line data with quantity and unit_paid (Money).
     * @return list<Money>
     */
    public function allocate(Money $shipping, string $method, array $lines): array
    {
        if (! in_array($method, ['quantity', 'merchandise_cost'], true)) {
            throw new InvalidArgumentException('El método de distribución del envío no es válido.');
        }

        if ($lines === [] || ! array_is_list($lines)) {
            throw new InvalidArgumentException('El reparto requiere líneas en orden estable.');
        }

        $totalWeight = 0;
        $lastEligibleIndex = null;

        foreach ($lines as $index => $line) {
            if (! is_array($line) || ! isset($line['quantity'], $line['unit_paid']) || ! is_int($line['quantity'])
                || $line['quantity'] <= 0 || ! $line['unit_paid'] instanceof Money) {
                throw new InvalidArgumentException('Cada línea necesita una cantidad positiva y un costo pagado válido.');
            }

            $unitWeight = $method === 'quantity' ? 1 : $line['unit_paid']->cents;
            $totalWeight = DecimalNumber::checkedAdd(
                $totalWeight,
                DecimalNumber::checkedMultiply($line['quantity'], $unitWeight),
            );

            if ($unitWeight > 0) {
                $lastEligibleIndex = $index;
            }
        }

        if ($totalWeight === 0 && $shipping->cents > 0) {
            throw new InvalidArgumentException('No hay mercadería pagada para distribuir el envío por costo.');
        }

        $allocated = 0;
        $lineCents = [];

        foreach ($lines as $line) {
            $unitWeight = $method === 'quantity' ? 1 : $line['unit_paid']->cents;
            $unitShare = $totalWeight === 0 ? 0 : intdiv(
                DecimalNumber::checkedMultiply($shipping->cents, $unitWeight),
                $totalWeight,
            );
            $lineShare = DecimalNumber::checkedMultiply($unitShare, $line['quantity']);
            $lineCents[] = $lineShare;
            $allocated = DecimalNumber::checkedAdd($allocated, $lineShare);
        }

        if ($lastEligibleIndex !== null) {
            $lineCents[$lastEligibleIndex] = DecimalNumber::checkedAdd(
                $lineCents[$lastEligibleIndex],
                $shipping->cents - $allocated,
            );
        }

        return array_values(array_map(fn (int $cents): Money => Money::fromCents($cents), $lineCents));
    }
}
