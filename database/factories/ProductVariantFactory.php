<?php

namespace Database\Factories;

use App\Models\ProductGroup;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_group_id' => ProductGroup::factory(),
            'sku' => fake()->unique()->bothify('EJ-#####'),
            'name' => fake()->words(2, true),
            'is_active' => true,
            'minimum_stock' => 0,
            'markup_override_decimal' => null,
        ];
    }
}
