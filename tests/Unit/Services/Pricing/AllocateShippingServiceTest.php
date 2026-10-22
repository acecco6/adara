<?php

namespace Tests\Unit\Services\Pricing;

use App\Services\Pricing\AllocateShippingService;
use App\Services\Pricing\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RangeException;

class AllocateShippingServiceTest extends TestCase
{
    public function test_quantity_distribution_preserves_every_cent_and_gives_remainder_to_last_unit(): void
    {
        $lines = [
            ['quantity' => 1, 'unit_paid' => Money::fromDecimal('10.00')],
            ['quantity' => 1, 'unit_paid' => Money::fromDecimal('20.00')],
            ['quantity' => 1, 'unit_paid' => Money::fromDecimal('30.00')],
        ];

        $shares = (new AllocateShippingService)->allocate(Money::fromDecimal('1000.00'), 'quantity', $lines);

        $this->assertSame(['333.33', '333.33', '333.34'], array_map(fn (Money $money): string => $money->toDecimal(), $shares));
    }

    public function test_methods_distribute_differently_for_unequal_prices(): void
    {
        $lines = [
            ['quantity' => 2, 'unit_paid' => Money::fromDecimal('10.00')],
            ['quantity' => 1, 'unit_paid' => Money::fromDecimal('20.00')],
        ];
        $service = new AllocateShippingService;

        $byQuantity = $service->allocate(Money::fromDecimal('1000.00'), 'quantity', $lines);
        $byCost = $service->allocate(Money::fromDecimal('1000.00'), 'merchandise_cost', $lines);

        $this->assertSame(['666.66', '333.34'], array_map(fn (Money $money): string => $money->toDecimal(), $byQuantity));
        $this->assertSame(['500.00', '500.00'], array_map(fn (Money $money): string => $money->toDecimal(), $byCost));
    }

    public function test_cost_distribution_rounds_each_unit_down_before_last_remainder(): void
    {
        $lines = [
            ['quantity' => 2, 'unit_paid' => Money::fromDecimal('10.00')],
            ['quantity' => 1, 'unit_paid' => Money::fromDecimal('20.00')],
        ];

        $shares = (new AllocateShippingService)->allocate(Money::fromDecimal('0.02'), 'merchandise_cost', $lines);

        $this->assertSame(['0.00', '0.02'], array_map(fn (Money $money): string => $money->toDecimal(), $shares));
    }

    public function test_remainder_skips_zero_weight_last_line(): void
    {
        $lines = [
            ['quantity' => 1, 'unit_paid' => Money::fromDecimal('1.00')],
            ['quantity' => 1, 'unit_paid' => Money::fromDecimal('0.00')],
        ];

        $shares = (new AllocateShippingService)->allocate(Money::fromDecimal('0.01'), 'merchandise_cost', $lines);

        $this->assertSame(['0.01', '0.00'], array_map(fn (Money $money): string => $money->toDecimal(), $shares));
    }

    public function test_large_quantities_are_distributed_without_expanding_unit_arrays(): void
    {
        $lines = [
            ['quantity' => 1_000_000, 'unit_paid' => Money::fromDecimal('1.00')],
            ['quantity' => 1_000_000, 'unit_paid' => Money::fromDecimal('1.00')],
        ];

        $shares = (new AllocateShippingService)->allocate(Money::fromDecimal('0.01'), 'quantity', $lines);

        $this->assertSame(['0.00', '0.01'], array_map(fn (Money $money): string => $money->toDecimal(), $shares));
    }

    public function test_general_and_additional_shipping_can_be_combined_without_duplication(): void
    {
        $service = new AllocateShippingService;
        $general = $service->allocate(Money::fromDecimal('12000.00'), 'quantity', [
            ['quantity' => 8, 'unit_paid' => Money::fromDecimal('100.00')],
            ['quantity' => 4, 'unit_paid' => Money::fromDecimal('100.00')],
        ]);
        $additional = $service->allocate(Money::fromDecimal('4000.00'), 'quantity', [
            ['quantity' => 4, 'unit_paid' => Money::fromDecimal('100.00')],
        ]);

        $this->assertSame(['8000.00', '4000.00'], array_map(fn (Money $money): string => $money->toDecimal(), $general));
        $this->assertSame('4000.00', $additional[0]->toDecimal());
        $this->assertSame(1_600_000, $general[0]->cents + $general[1]->cents + $additional[0]->cents);
    }

    public function test_zero_shipping_needs_no_paid_cost_basis(): void
    {
        $shares = (new AllocateShippingService)->allocate(Money::fromCents(0), 'merchandise_cost', [
            ['quantity' => 2, 'unit_paid' => Money::fromCents(0)],
        ]);

        $this->assertSame([0], array_map(fn (Money $money): int => $money->cents, $shares));
    }

    public function test_rejects_invalid_method_and_unordered_lines(): void
    {
        $line = ['quantity' => 1, 'unit_paid' => Money::fromDecimal('1.00')];
        $service = new AllocateShippingService;

        foreach ([['other', [$line]], ['quantity', ['sku' => $line]]] as [$method, $lines]) {
            try {
                $service->allocate(Money::fromDecimal('1.00'), $method, $lines);
                $this->fail('La distribución inválida debía rechazarse.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_rejects_zero_quantity_and_positive_shipping_without_cost_basis(): void
    {
        $service = new AllocateShippingService;

        foreach ([
            ['quantity', [['quantity' => 0, 'unit_paid' => Money::fromDecimal('1.00')]]],
            ['merchandise_cost', [['quantity' => 1, 'unit_paid' => Money::fromDecimal('0.00')]]],
        ] as [$method, $lines]) {
            try {
                $service->allocate(Money::fromDecimal('1.00'), $method, $lines);
                $this->fail('La base inválida debía rechazarse.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_rejects_malformed_line_data(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AllocateShippingService)->allocate(Money::fromDecimal('1.00'), 'quantity', [
            ['quantity' => 2, 'unit_paid' => '10.00'],
        ]);
    }

    public function test_rejects_intermediate_overflow_without_float_fallback(): void
    {
        $this->expectException(RangeException::class);

        (new AllocateShippingService)->allocate(Money::fromCents(PHP_INT_MAX), 'merchandise_cost', [
            ['quantity' => 1, 'unit_paid' => Money::fromCents(2)],
        ]);
    }
}
