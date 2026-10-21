<?php

namespace Tests\Unit\Services\Pricing;

use App\Services\Pricing\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RangeException;

class MoneyTest extends TestCase
{
    public function test_parses_ars_cents_without_losing_precision(): void
    {
        $amount = Money::fromDecimal('18500.25');

        $this->assertSame(1_850_025, $amount->cents);
        $this->assertSame('18500.25', $amount->toDecimal());
    }

    public function test_preserves_one_cent_and_formats_whole_pesos(): void
    {
        $this->assertSame(1, Money::fromDecimal('0.01')->cents);
        $this->assertSame('15000.00', Money::fromDecimal('15000')->toDecimal());
    }

    public function test_rejects_localized_or_excess_precision_input(): void
    {
        foreach (['1.000,25', '1,25', '01.00', '1.001', '-1.00', '1e3'] as $invalid) {
            try {
                Money::fromDecimal($invalid);
                $this->fail("Se aceptó un importe inválido: {$invalid}");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_accepts_maximum_integer_cents_and_rejects_overflow(): void
    {
        $this->assertSame(PHP_INT_MAX, Money::fromDecimal('92233720368547758.07')->cents);

        $this->expectException(RangeException::class);

        Money::fromDecimal('92233720368547758.08');
    }

    public function test_rejects_negative_cents(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(-1);
    }

    public function test_rejects_float_cents_without_coercion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(1.5);
    }

    public function test_rejects_float_decimal_input_without_coercion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal(1.25);
    }
}
