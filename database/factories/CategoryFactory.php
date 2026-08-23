<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Category $category): void {
            if ($category->translations()->exists()) {
                return;
            }

            CategoryTranslation::query()->create([
                'category_id' => $category->id,
                'locale' => 'en',
                'name' => Str::title(str_replace('-', ' ', $category->slug)),
            ]);

            CategoryTranslation::query()->create([
                'category_id' => $category->id,
                'locale' => 'ar',
                'name' => 'تصنيف '.$category->id,
            ]);
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }
}
