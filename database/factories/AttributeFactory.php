<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    protected $model = Attribute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'code' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Attribute $attribute): void {
            if ($attribute->translations()->exists()) {
                return;
            }

            AttributeTranslation::query()->create([
                'attribute_id' => $attribute->id,
                'locale' => 'en',
                'name' => Str::title(str_replace('-', ' ', $attribute->code)),
            ]);

            AttributeTranslation::query()->create([
                'attribute_id' => $attribute->id,
                'locale' => 'ar',
                'name' => 'سمة '.$attribute->id,
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
