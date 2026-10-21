<?php

namespace Tests\Unit\Services\Pricing;

use App\Services\Pricing\Percentage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PercentageTest extends TestCase
{
    public function test_reduces_decimal_percentage_to_exact_fraction(): void
    {
        $percentage = Percentage::fromDecimal('12.5');

        $this->assertSame(25, $percentage->numerator);
        $this->assertSame(2, $percentage->denominator);
    }

    public function test_preserves_small_decimal_percentage_without_float(): void
    {
        $percentage = Percentage::fromDecimal('0.0001');

        $this->assertSame(1, $percentage->numerator);
        $this->assertSame(10_000, $percentage->denominator);
    }

    public function test_rejects_negative_percentage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Percentage::fromDecimal('-0.1');
    }

    public function test_rejects_float_percentage_without_coercion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Percentage::fromDecimal(12.5);
    }
}
