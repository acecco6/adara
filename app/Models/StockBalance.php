<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    protected $primaryKey = 'product_variant_id';

    public $incrementing = false;

    public $timestamps = false;

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity_on_hand' => 'integer', 'updated_at' => 'datetime'];
    }
}
