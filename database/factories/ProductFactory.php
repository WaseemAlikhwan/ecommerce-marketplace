<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'store_id' => Store::factory(),
            'category_id' => null,
            'brand_id' => null,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'type' => ProductType::Simple,
            'status' => ProductStatus::Draft,
            'currency_code' => 'SYP',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            if (! $product->translations()->exists()) {
                ProductTranslation::query()->create([
                    'product_id' => $product->id,
                    'locale' => 'en',
                    'name' => Str::title(str_replace('-', ' ', $product->slug)),
                ]);
            }

            if ($product->type !== ProductType::Simple) {
                return;
            }

            if (! $product->variants()->withTrashed()->exists()) {
                $variant = ProductVariant::factory()->create([
                    'product_id' => $product->id,
                    'store_id' => $product->store_id,
                    'combination_key' => ProductVariant::DEFAULT_COMBINATION_KEY,
                ]);

                $product->forceFill(['default_variant_id' => $variant->id])->saveQuietly();

                return;
            }

            if ($product->default_variant_id === null) {
                $variant = ProductVariant::withTrashed()
                    ->where('product_id', $product->id)
                    ->orderBy('id')
                    ->first();

                if ($variant !== null) {
                    $product->forceFill(['default_variant_id' => $variant->id])->saveQuietly();
                }
            }
        });
    }

    public function variable(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Variable,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Archived,
            'deleted_at' => now(),
        ]);
    }
}
