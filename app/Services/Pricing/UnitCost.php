<?php

namespace App\Services\Pricing;

use InvalidArgumentException;

final readonly class UnitCost
{
    public const SCALE = 10_000;

    private function __construct(public int $tenThousandths) {}

    public static function fromTenThousandths(mixed $value): self
    {
        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException('El costo unitario debe ser un entero no negativo.');
        }

        return new self($value);
    }

    public static function fromDecimal(mixed $value): self
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('El costo unitario debe ingresarse como cadena decimal.');
        }

        return new self(DecimalNumber::parseUnsigned($value, 4));
    }

    public function toDecimal(): string
    {
        return DecimalNumber::formatUnsigned($this->tenThousandths, 4);
    }
}
