<?php

namespace App\Services\Pricing;

use InvalidArgumentException;

final readonly class Percentage
{
    private function __construct(public int $numerator, public int $denominator) {}

    public static function fromDecimal(mixed $value): self
    {
        if (! is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]+)?\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Se espera un porcentaje decimal canónico no negativo.');
        }

        $dotPosition = strpos($value, '.');
        $scale = $dotPosition === false ? 0 : strlen($value) - $dotPosition - 1;
        $numerator = DecimalNumber::parseUnsigned($value, $scale);
        $denominator = 10 ** $scale;
        $divisor = self::greatestCommonDivisor($numerator, $denominator);

        return new self(intdiv($numerator, $divisor), intdiv($denominator, $divisor));
    }

    private static function greatestCommonDivisor(int $left, int $right): int
    {
        while ($right !== 0) {
            [$left, $right] = [$right, $left % $right];
        }

        return $left;
    }
}
