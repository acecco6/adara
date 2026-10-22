<?php

namespace App\Services\Pricing;

use InvalidArgumentException;

class AllocateSaleDiscountService
{
    /**
     * @param  list<array{quantity: int, unit_price_cents: int}>  $lines
     * @return list<array{base_per_unit_cents: int, discount_cents: int}>
     */
    public function allocate(int $discountCents, array $lines): array
    {
        if ($lines === [] || $discountCents < 0) {
            throw new InvalidArgumentException('El descuento y los renglones deben ser válidos.');
        }

        $subtotal = 0;

        foreach ($lines as $line) {
            if ($line['quantity'] <= 0 || $line['unit_price_cents'] <= 0) {
                throw new InvalidArgumentException('Cada renglón necesita cantidad y precio positivos.');
            }

            $subtotal = DecimalNumber::checkedAdd(
                $subtotal,
                DecimalNumber::checkedMultiply($line['quantity'], $line['unit_price_cents']),
            );
        }

        if ($discountCents > $subtotal) {
            throw new InvalidArgumentException('El descuento supera el subtotal.');
        }

        $allocated = 0;
        $shares = [];

        foreach ($lines as $line) {
            $basePerUnit = intdiv(DecimalNumber::checkedMultiply($discountCents, $line['unit_price_cents']), $subtotal);
            $lineShare = DecimalNumber::checkedMultiply($basePerUnit, $line['quantity']);
            $shares[] = ['base_per_unit_cents' => $basePerUnit, 'discount_cents' => $lineShare];
            $allocated = DecimalNumber::checkedAdd($allocated, $lineShare);
        }

        $remainder = $discountCents - $allocated;

        for ($index = count($lines) - 1; $index >= 0 && $remainder > 0; $index--) {
            $capacityPerUnit = $lines[$index]['unit_price_cents'] - $shares[$index]['base_per_unit_cents'];
            $capacity = DecimalNumber::checkedMultiply($capacityPerUnit, $lines[$index]['quantity']);
            $extra = min($remainder, $capacity);
            $shares[$index] = [
                'base_per_unit_cents' => $shares[$index]['base_per_unit_cents'],
                'discount_cents' => DecimalNumber::checkedAdd($shares[$index]['discount_cents'], $extra),
            ];
            $remainder -= $extra;
        }

        return $shares;
    }
}
