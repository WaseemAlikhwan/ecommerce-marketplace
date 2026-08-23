<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Brand $brand): void {
            if ($brand->translations()->exists()) {
                return;
            }

            BrandTranslation::query()->create([
                'brand_id' => $brand->id,
                'locale' => 'en',
                'name' => Str::title(str_replace('-', ' ', $brand->slug)),
            ]);

            BrandTranslation::query()->create([
                'brand_id' => $brand->id,
                'locale' => 'ar',
                'name' => 'علامة '.$brand->id,
            ]);
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
