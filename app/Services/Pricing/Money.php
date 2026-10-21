<?php

namespace App\Services\Pricing;

use InvalidArgumentException;

final readonly class Money
{
    private function __construct(public int $cents) {}

    public static function fromCents(mixed $cents): self
    {
        if (! is_int($cents) || $cents < 0) {
            throw new InvalidArgumentException('El dinero debe ser un entero de centavos no negativo.');
        }

        return new self($cents);
    }

    public static function fromDecimal(mixed $value): self
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('El dinero debe ingresarse como cadena decimal.');
        }

        return new self(DecimalNumber::parseUnsigned($value, 2));
    }

    public function toDecimal(): string
    {
        return DecimalNumber::formatUnsigned($this->cents, 2);
    }
}
