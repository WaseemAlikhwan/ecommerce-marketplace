<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory();

        return [
            'product_id' => $product,
            'store_id' => fn (array $attributes) => Product::query()->findOrFail($attributes['product_id'])->store_id,
            'path' => 'products/'.fake()->unique()->numerify('####').'/'.Str::ulid().'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12_000,
            'width' => 800,
            'height' => 800,
            'position' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ProductImage $image): void {
            if ($image->product_id && ! $image->store_id) {
                $image->store_id = Product::query()->findOrFail($image->product_id)->store_id;
            }

            if ($image->product_id && str_starts_with((string) $image->path, 'products/') === false) {
                $image->path = 'products/'.$image->product_id.'/'.Str::ulid().'.jpg';
            }
        });
    }
}
