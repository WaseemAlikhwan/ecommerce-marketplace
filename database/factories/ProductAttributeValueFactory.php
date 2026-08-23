<?php

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAttributeValue>
 */
class ProductAttributeValueFactory extends Factory
{
    protected $model = ProductAttributeValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ProductAttributeValue $row): void {
            if ($row->product_id && $row->product_attribute_id && $row->attribute_id && $row->attribute_value_id) {
                return;
            }

            $assignment = $row->product_attribute_id
                ? ProductAttribute::withTrashed()->findOrFail($row->product_attribute_id)
                : ProductAttribute::factory()->create([
                    'product_id' => $row->product_id ?: Product::factory(),
                    'attribute_id' => $row->attribute_id ?: Attribute::factory(),
                ]);

            $value = $row->attribute_value_id
                ? AttributeValue::query()->findOrFail($row->attribute_value_id)
                : AttributeValue::factory()->create([
                    'attribute_id' => $assignment->attribute_id,
                ]);

            $row->product_id = $assignment->product_id;
            $row->product_attribute_id = $assignment->id;
            $row->attribute_id = $assignment->attribute_id;
            $row->attribute_value_id = $value->id;
        });
    }
}
