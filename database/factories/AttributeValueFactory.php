<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\AttributeValueTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttributeValue>
 */
class AttributeValueFactory extends Factory
{
    protected $model = AttributeValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'attribute_id' => Attribute::factory(),
            'code' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (AttributeValue $value): void {
            if ($value->translations()->exists()) {
                return;
            }

            AttributeValueTranslation::query()->create([
                'attribute_value_id' => $value->id,
                'locale' => 'en',
                'name' => Str::title(str_replace('-', ' ', $value->code)),
            ]);

            AttributeValueTranslation::query()->create([
                'attribute_value_id' => $value->id,
                'locale' => 'ar',
                'name' => 'قيمة '.$value->id,
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
