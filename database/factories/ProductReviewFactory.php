<?php

namespace Database\Factories;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductReview>
 */
class ProductReviewFactory extends Factory
{
    protected $model = ProductReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'body' => fake()->optional()->sentence(),
            'status' => ProductReviewStatus::Pending,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => ProductReviewStatus::Pending]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => ProductReviewStatus::Approved]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => ProductReviewStatus::Rejected]);
    }
}
