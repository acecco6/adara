<?php

namespace App\Services\Pricing;

use InvalidArgumentException;
use RangeException;

class DecimalNumber
{
    public static function parseUnsigned(string $value, int $scale): int
    {
        if ($scale < 0 || $scale > 17 || preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]+)?\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Se espera un número decimal canónico no negativo.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        if (strlen($fraction) > $scale) {
            throw new InvalidArgumentException('El importe supera la precisión decimal permitida.');
        }

        $factor = 10 ** $scale;
        $maximumWhole = (string) intdiv(PHP_INT_MAX, $factor);

        if (strlen($whole) > strlen($maximumWhole) || (strlen($whole) === strlen($maximumWhole) && strcmp($whole, $maximumWhole) > 0)) {
            throw new RangeException('El importe excede el rango entero admitido.');
        }

        $wholeUnits = (int) $whole * $factor;
        $fractionUnits = $fraction === '' ? 0 : (int) str_pad($fraction, $scale, '0');

        return self::checkedAdd($wholeUnits, $fractionUnits);
    }

    public static function formatUnsigned(int $value, int $scale): string
    {
        if ($value < 0 || $scale < 0 || $scale > 17) {
            throw new InvalidArgumentException('El importe o la escala son inválidos.');
        }

        $factor = 10 ** $scale;

        if ($scale === 0) {
            return (string) $value;
        }

        return intdiv($value, $factor).'.'.str_pad((string) ($value % $factor), $scale, '0', STR_PAD_LEFT);
    }

    public static function checkedAdd(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || $left > PHP_INT_MAX - $right) {
            throw new RangeException('La operación excede el rango entero admitido.');
        }

        return $left + $right;
    }

    public static function checkedMultiply(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || ($right !== 0 && $left > intdiv(PHP_INT_MAX, $right))) {
            throw new RangeException('La operación excede el rango entero admitido.');
        }

        return $left * $right;
    }
}
