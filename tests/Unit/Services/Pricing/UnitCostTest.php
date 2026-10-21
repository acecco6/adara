<?php

namespace Tests\Unit\Services\Pricing;

use App\Services\Pricing\UnitCost;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UnitCostTest extends TestCase
{
    public function test_preserves_four_decimal_places(): void
    {
        $cost = UnitCost::fromDecimal('8453.2750');

        $this->assertSame(84_532_750, $cost->tenThousandths);
        $this->assertSame('8453.2750', $cost->toDecimal());
    }

    public function test_preserves_smallest_unit_cost_fraction(): void
    {
        $this->assertSame(1, UnitCost::fromDecimal('0.0001')->tenThousandths);
    }

    public function test_rejects_a_fifth_decimal_place(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UnitCost::fromDecimal('1.00001');
    }

    public function test_rejects_negative_cost(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UnitCost::fromTenThousandths(-1);
    }

    public function test_rejects_float_unit_cost_without_coercion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UnitCost::fromTenThousandths(1.5);
    }
}
