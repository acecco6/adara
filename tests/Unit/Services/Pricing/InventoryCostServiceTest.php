<?php

namespace Tests\Unit\Services\Pricing;

use App\Services\Pricing\InventoryCostService;
use App\Services\Pricing\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RangeException;

class InventoryCostServiceTest extends TestCase
{
    public function test_adds_shipping_once_and_keeps_four_decimal_places(): void
    {
        $cost = (new InventoryCostService)->unitCostForReceipt(
            Money::fromDecimal('20.00'), Money::fromDecimal('1.00'), 3,
        );

        $this->assertSame('7.0000', $cost->toDecimal());
    }

    public function test_rounds_only_the_final_unit_cost_half_up(): void
    {
        $service = new InventoryCostService;

        $this->assertSame('3.3367', $service->unitCostForReceipt(Money::fromDecimal('10.00'), Money::fromDecimal('0.01'), 3)->toDecimal());
        $this->assertSame('0.0017', $service->unitCostForReceipt(Money::fromDecimal('0.01'), Money::fromDecimal('0.00'), 6)->toDecimal());
        $this->assertSame('0.0050', $service->unitCostForReceipt(Money::fromDecimal('0.01'), Money::fromDecimal('0.00'), 2)->toDecimal());
    }

    public function test_rejects_nonpositive_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InventoryCostService)->unitCostForReceipt(Money::fromDecimal('1.00'), Money::fromDecimal('0.00'), 0);
    }

    public function test_rejects_overflow_without_float_fallback(): void
    {
        $this->expectException(RangeException::class);

        (new InventoryCostService)->unitCostForReceipt(Money::fromCents(PHP_INT_MAX), Money::fromCents(1), 1);
    }
}
