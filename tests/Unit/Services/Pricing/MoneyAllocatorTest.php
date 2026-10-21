<?php

namespace Tests\Unit\Services\Pricing;

use App\Services\Pricing\Money;
use App\Services\Pricing\MoneyAllocator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RangeException;

class MoneyAllocatorTest extends TestCase
{
    public function test_assigns_last_cent_to_last_unit_in_stable_order(): void
    {
        $shares = (new MoneyAllocator)->allocate(Money::fromDecimal('1000.00'), [1, 1, 1]);

        $this->assertSame([33_333, 33_333, 33_334], array_map(fn (Money $share): int => $share->cents, $shares));
    }

    public function test_assigns_one_cent_only_to_last_eligible_unit(): void
    {
        $shares = (new MoneyAllocator)->allocate(Money::fromDecimal('0.01'), [1, 1, 0]);

        $this->assertSame([0, 1, 0], array_map(fn (Money $share): int => $share->cents, $shares));
    }

    public function test_assigns_all_remaining_cents_to_the_last_eligible_unit(): void
    {
        $shares = (new MoneyAllocator)->allocate(Money::fromDecimal('0.02'), [1, 1, 1, 1]);

        $this->assertSame([0, 0, 0, 2], array_map(fn (Money $share): int => $share->cents, $shares));
    }

    public function test_weighted_shares_preserve_exact_total(): void
    {
        $shares = (new MoneyAllocator)->allocate(Money::fromDecimal('10.00'), [1, 2, 3]);

        $this->assertSame([166, 333, 501], array_map(fn (Money $share): int => $share->cents, $shares));
        $this->assertSame(1_000, array_sum(array_map(fn (Money $share): int => $share->cents, $shares)));
    }

    public function test_rejects_missing_distribution_basis(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MoneyAllocator)->allocate(Money::fromDecimal('1.00'), [0, 0]);
    }

    public function test_rejects_unstable_associative_weights(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MoneyAllocator)->allocate(Money::fromDecimal('1.00'), ['sku-b' => 1, 'sku-a' => 1]);
    }

    public function test_rejects_negative_weights(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MoneyAllocator)->allocate(Money::fromDecimal('1.00'), [1, -1]);
    }

    public function test_rejects_fractional_weights(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MoneyAllocator)->allocate(Money::fromDecimal('1.00'), [1, 0.5]);
    }

    public function test_rejects_intermediate_overflow_without_float_fallback(): void
    {
        $this->expectException(RangeException::class);

        (new MoneyAllocator)->allocate(Money::fromCents(PHP_INT_MAX), [2, 1]);
    }
}
