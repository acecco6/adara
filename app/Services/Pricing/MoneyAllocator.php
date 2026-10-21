<?php

namespace App\Services\Pricing;

use InvalidArgumentException;

final class MoneyAllocator
{
    /**
     * The caller must provide weights in a persisted, stable order.
     *
     * @param  array<array-key, mixed>  $weights
     * @return list<Money>
     */
    public function allocate(Money $total, array $weights): array
    {
        if ($weights === [] || ! array_is_list($weights)) {
            throw new InvalidArgumentException('El reparto requiere una lista ordenada de pesos.');
        }

        $totalWeight = 0;

        foreach ($weights as $weight) {
            if (! is_int($weight) || $weight < 0) {
                throw new InvalidArgumentException('Los pesos deben ser enteros no negativos.');
            }

            $totalWeight = DecimalNumber::checkedAdd($totalWeight, $weight);
        }

        if ($totalWeight === 0) {
            throw new InvalidArgumentException('Al menos un peso debe ser positivo.');
        }

        $shares = [];
        $allocated = 0;

        foreach ($weights as $weight) {
            $weightedTotal = DecimalNumber::checkedMultiply($total->cents, $weight);
            $share = intdiv($weightedTotal, $totalWeight);
            $shares[] = $share;
            $allocated = DecimalNumber::checkedAdd($allocated, $share);
        }

        $remainder = $total->cents - $allocated;

        for ($index = count($weights) - 1; $index >= 0; $index--) {
            if ($weights[$index] > 0) {
                $shares[$index] = DecimalNumber::checkedAdd($shares[$index], $remainder);

                break;
            }
        }

        $allocatedAmounts = [];

        foreach ($shares as $share) {
            $allocatedAmounts[] = Money::fromCents($share);
        }

        return $allocatedAmounts;
    }
}
