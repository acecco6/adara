<?php

namespace App\Services\Pricing;

use InvalidArgumentException;

class InventoryCostService
{
    public function unitCostForReceipt(Money $merchandisePaid, Money $allocatedShipping, int $quantity): UnitCost
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad recibida debe ser positiva.');
        }

        $totalCents = DecimalNumber::checkedAdd($merchandisePaid->cents, $allocatedShipping->cents);
        $totalTenThousandths = DecimalNumber::checkedMultiply($totalCents, 100);
        $unitTenThousandths = intdiv($totalTenThousandths, $quantity);
        $remainder = $totalTenThousandths % $quantity;

        if ($remainder >= intdiv($quantity, 2) + ($quantity % 2)) {
            $unitTenThousandths = DecimalNumber::checkedAdd($unitTenThousandths, 1);
        }

        return UnitCost::fromTenThousandths($unitTenThousandths);
    }
}
