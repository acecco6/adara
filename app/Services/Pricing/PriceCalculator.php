<?php

namespace App\Services\Pricing;

final class PriceCalculator
{
    public function catalogPrice(UnitCost $replacementCost, Percentage $markup): Money
    {
        if ($replacementCost->tenThousandths === 0) {
            return Money::fromCents(0);
        }

        $percentageBase = DecimalNumber::checkedMultiply(100, $markup->denominator);
        $factor = DecimalNumber::checkedAdd($percentageBase, $markup->numerator);
        $unroundedNumerator = DecimalNumber::checkedMultiply($replacementCost->tenThousandths, $factor);
        $hundredPesosInCostUnits = DecimalNumber::checkedMultiply(100, UnitCost::SCALE);
        $block = DecimalNumber::checkedMultiply($percentageBase, $hundredPesosInCostUnits);
        $hundredPesoBlocks = intdiv($unroundedNumerator, $block);

        if ($unroundedNumerator % $block !== 0) {
            $hundredPesoBlocks = DecimalNumber::checkedAdd($hundredPesoBlocks, 1);
        }

        return Money::fromCents(DecimalNumber::checkedMultiply($hundredPesoBlocks, 10_000));
    }
}
