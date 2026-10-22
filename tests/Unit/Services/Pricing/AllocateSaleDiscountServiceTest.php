<?php

namespace Tests\Unit\Services\Pricing;

use App\Services\Pricing\AllocateSaleDiscountService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AllocateSaleDiscountServiceTest extends TestCase
{
    public function test_remainder_is_assigned_to_last_unit_in_stable_order(): void
    {
        $shares = (new AllocateSaleDiscountService)->allocate(1, [
            ['quantity' => 1, 'unit_price_cents' => 100],
            ['quantity' => 1, 'unit_price_cents' => 100],
            ['quantity' => 1, 'unit_price_cents' => 100],
        ]);

        $this->assertSame([0, 0, 1], array_column($shares, 'discount_cents'));
    }

    public function test_large_remainder_moves_back_when_last_unit_reaches_its_price(): void
    {
        $shares = (new AllocateSaleDiscountService)->allocate(10000, [
            ['quantity' => 10000, 'unit_price_cents' => 1],
            ['quantity' => 1, 'unit_price_cents' => 1],
        ]);

        $this->assertSame([9999, 1], array_column($shares, 'discount_cents'));
        $this->assertSame(10000, array_sum(array_column($shares, 'discount_cents')));
    }

    public function test_discount_cannot_exceed_subtotal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AllocateSaleDiscountService)->allocate(101, [['quantity' => 1, 'unit_price_cents' => 100]]);
    }
}
