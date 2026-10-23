<?php

namespace Database\Factories;

use App\Models\ProductGroup;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');

        return [
            'product_group_id' => ProductGroup::factory(),
            'mime_type' => 'image/png',
            'content_bytes' => $pixel,
            'size_bytes' => strlen($pixel),
            'position' => 1,
            'is_primary' => true,
        ];
    }
}
