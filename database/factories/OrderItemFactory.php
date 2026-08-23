<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\VendorOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->numberBetween(100, 50_000);

        return [
            'vendor_order_id' => VendorOrder::factory(),
            'product_id' => null,
            'variant_id' => null,
            'store_id' => null,
            'vendor_id' => null,
            'quantity' => $quantity,
            'unit_price_amount_minor' => $unitPrice,
            'line_total_amount_minor' => $quantity * $unitPrice,
            'currency_code' => 'SYP',
            'product_name_ar' => 'منتج تجريبي',
            'product_name_en' => 'Test Product',
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'store_name' => fake()->company(),
        ];
    }
}
