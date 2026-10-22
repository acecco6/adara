<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<SaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('line_position');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'discount_base_cents' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'cost_total_snapshot_10000' => 'integer',
            'cost_total_snapshot_cents' => 'integer',
            'initial_paid_cents' => 'integer',
            'initial_balance_cents' => 'integer',
            'below_cost_accepted' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }
}
