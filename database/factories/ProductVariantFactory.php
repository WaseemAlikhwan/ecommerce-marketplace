<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'combination_key' => 'a'.fake()->unique()->numberBetween(100000, 999999).':v'.fake()->unique()->numberBetween(100000, 999999),
            'price_amount_minor' => 1000,
            'compare_at_amount_minor' => null,
            'quantity' => 10,
        ];
    }
}
