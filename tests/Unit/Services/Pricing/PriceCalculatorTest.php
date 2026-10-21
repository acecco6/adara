<?php

namespace Tests\Unit\Services\Pricing;

use App\Services\Pricing\Percentage;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\UnitCost;
use PHPUnit\Framework\TestCase;
use RangeException;

class PriceCalculatorTest extends TestCase
{
    public function test_rounds_up_to_next_hundred_pesos(): void
    {
        $price = new PriceCalculator;

        $this->assertSame('14300.00', $price->catalogPrice(UnitCost::fromDecimal('14250'), Percentage::fromDecimal('0'))->toDecimal());
        $this->assertSame('15000.00', $price->catalogPrice(UnitCost::fromDecimal('15000'), Percentage::fromDecimal('0'))->toDecimal());
    }

    public function test_fraction_above_exact_hundred_rounds_up(): void
    {
        $price = new PriceCalculator;

        $this->assertSame('200.00', $price->catalogPrice(UnitCost::fromDecimal('100.0001'), Percentage::fromDecimal('0'))->toDecimal());
    }

    public function test_calculates_decimal_markup_exactly(): void
    {
        $price = new PriceCalculator;

        $this->assertSame('11300.00', $price->catalogPrice(UnitCost::fromDecimal('10000'), Percentage::fromDecimal('12.5'))->toDecimal());
        $this->assertSame('17500.00', $price->catalogPrice(UnitCost::fromDecimal('10000'), Percentage::fromDecimal('75'))->toDecimal());
    }

    public function test_zero_cost_has_zero_catalog_price(): void
    {
        $price = new PriceCalculator;

        $this->assertSame('0.00', $price->catalogPrice(UnitCost::fromDecimal('0'), Percentage::fromDecimal('200'))->toDecimal());
    }

    public function test_rejects_intermediate_integer_overflow_instead_of_using_float(): void
    {
        $price = new PriceCalculator;

        $this->expectException(RangeException::class);

        $price->catalogPrice(UnitCost::fromTenThousandths(PHP_INT_MAX), Percentage::fromDecimal('200'));
    }
}
