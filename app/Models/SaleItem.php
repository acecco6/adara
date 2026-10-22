<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'line_position' => 'integer',
            'quantity' => 'integer',
            'unit_price_snapshot_cents' => 'integer',
            'gross_cents' => 'integer',
            'discount_base_per_unit_cents' => 'integer',
            'discount_cents' => 'integer',
            'net_cents' => 'integer',
            'unit_cost_snapshot_10000' => 'integer',
            'cost_total_snapshot_10000' => 'integer',
            'cost_total_snapshot_cents' => 'integer',
        ];
    }
}
