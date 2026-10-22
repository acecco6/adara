<?php

namespace App\Services\Pricing;

use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleQuoteService
{
    public function __construct(private AllocateSaleDiscountService $discounts) {}

    /**
     * @param  list<array{variant_id: int, quantity: int}>  $lines
     * @return array{subtotal: int, discount: int, total: int, cost_total: int, below_cost: bool, items: list<array{variant_id: int, quantity: int, sku: string, presentation: string, product_name: string, price: int, gross: int, cost: int, cost_total: int, discount: int, discount_base_per_unit: int}>}
     */
    public function quote(array $lines, string $discountType, string $discountValue): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Agregá al menos una presentación al carrito.');
        }

        $variants = DB::table('product_variants')
            ->join('product_groups', 'product_groups.id', '=', 'product_variants.product_group_id')
            ->join('stock_balances', 'stock_balances.product_variant_id', '=', 'product_variants.id')
            ->whereIn('product_variants.id', array_column($lines, 'variant_id'))
            ->select('product_variants.id', 'product_variants.sku', 'product_variants.name', 'product_variants.is_active',
                'product_variants.current_price_cents', 'product_variants.current_unit_cost_10000',
                'product_groups.name as product_name', 'stock_balances.quantity_on_hand')
            ->get()->keyBy('id');

        $items = [];
        $subtotal = 0;
        $costTotal = 0;

        foreach ($lines as $line) {
            $variant = $variants->get($line['variant_id']);

            if ($line['quantity'] <= 0 || $variant === null || ! $variant->is_active || $variant->current_price_cents === null
                || (int) $variant->current_price_cents <= 0 || $variant->current_unit_cost_10000 === null
                || (int) $variant->quantity_on_hand < $line['quantity']) {
                throw new DomainException('Una presentación está inactiva, sin precio o sin stock suficiente.');
            }

            $price = (int) $variant->current_price_cents;
            $cost = (int) $variant->current_unit_cost_10000;
            $gross = DecimalNumber::checkedMultiply($line['quantity'], $price);
            $lineCost = DecimalNumber::checkedMultiply($line['quantity'], $cost);
            $subtotal = DecimalNumber::checkedAdd($subtotal, $gross);
            $costTotal = DecimalNumber::checkedAdd($costTotal, $lineCost);
            $items[] = [
                'variant_id' => $line['variant_id'], 'quantity' => $line['quantity'],
                'sku' => (string) $variant->sku, 'presentation' => (string) $variant->name,
                'product_name' => (string) $variant->product_name, 'price' => $price, 'gross' => $gross,
                'cost' => $cost, 'cost_total' => $lineCost, 'discount' => 0, 'discount_base_per_unit' => 0,
            ];
        }

        $discount = $this->discountCents($subtotal, $discountType, $discountValue);
        $total = $subtotal - $discount;

        if ($total === 0) {
            throw new DomainException('Una entrega gratuita se registra como ajuste de stock, no como venta.');
        }

        $shares = $this->discounts->allocate($discount, array_map(
            fn (array $item): array => ['quantity' => $item['quantity'], 'unit_price_cents' => $item['price']], $items,
        ));

        foreach ($items as $position => &$item) {
            $item['discount'] = $shares[$position]['discount_cents'];
            $item['discount_base_per_unit'] = $shares[$position]['base_per_unit_cents'];
        }
        unset($item);

        $belowCost = intdiv($costTotal, 100) > $total
            || (intdiv($costTotal, 100) === $total && $costTotal % 100 > 0);

        return [
            'subtotal' => $subtotal, 'discount' => $discount, 'total' => $total,
            'cost_total' => $costTotal, 'below_cost' => $belowCost, 'items' => $items,
        ];
    }

    /** @return array{string, string} */
    public function normalizeDiscount(string $type, string $input): array
    {
        $input = str_replace(',', '.', trim($input));

        if ($type === 'none' && $input === '0') {
            return ['none', '0'];
        }

        if ($type === 'fixed') {
            return ['fixed', Money::fromDecimal($input)->toDecimal()];
        }

        if ($type === 'percentage' && preg_match('/\A(?:0|[1-9][0-9]*)(?:\.[0-9]{1,4})?\z/D', $input) === 1) {
            $units = DecimalNumber::parseUnsigned($input, 4);

            if ($units <= 1_000_000) {
                return ['percentage', DecimalNumber::formatUnsigned($units, 4)];
            }
        }

        throw new InvalidArgumentException('El tipo o importe del descuento no es válido.');
    }

    private function discountCents(int $subtotal, string $type, string $value): int
    {
        if ($type === 'none') {
            return 0;
        }

        if ($type === 'fixed') {
            $discount = Money::fromDecimal($value)->cents;
        } else {
            $units = DecimalNumber::parseUnsigned($value, 4);
            $whole = DecimalNumber::checkedMultiply(intdiv($subtotal, 1_000_000), $units);
            $fraction = ($subtotal % 1_000_000) * $units;
            $discount = DecimalNumber::checkedAdd($whole, intdiv($fraction, 1_000_000));
            $discount = DecimalNumber::checkedAdd($discount, (int) (($fraction % 1_000_000) >= 500_000));
        }

        if ($discount > $subtotal) {
            throw new DomainException('El descuento no puede superar el subtotal.');
        }

        return $discount;
    }

    public function roundedCostCents(int $cost10000): int
    {
        return intdiv($cost10000, 100) + (int) ($cost10000 % 100 >= 50);
    }
}
