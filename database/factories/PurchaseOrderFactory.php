<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'number' => 'EJ-PED-'.fake()->unique()->numerify('######'),
            'status' => 'draft',
            'shipping_method' => 'quantity',
            'general_shipping_cents' => 0,
            'notes' => null,
            'confirmed_at' => null,
        ];
    }
}
