<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use App\Models\User;
use App\Services\ProductService;
use App\Support\CombinationKey;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VariableProductDomainTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     color: Attribute,
     *     size: Attribute,
     *     red: AttributeValue,
     *     blue: AttributeValue,
     *     small: AttributeValue,
     *     medium: AttributeValue
     * }
     */
    private function colorAndSize(): array
    {
        $color = Attribute::factory()->create(['code' => 'color-'.uniqid()]);
        $size = Attribute::factory()->create(['code' => 'size-'.uniqid()]);

        return [
            'color' => $color,
            'size' => $size,
            'red' => AttributeValue::factory()->for($color)->create(['code' => 'red-'.uniqid()]),
            'blue' => AttributeValue::factory()->for($color)->create(['code' => 'blue-'.uniqid()]),
            'small' => AttributeValue::factory()->for($size)->create(['code' => 's-'.uniqid()]),
            'medium' => AttributeValue::factory()->for($size)->create(['code' => 'm-'.uniqid()]),
        ];
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function variablePayload(array $attrs, array $overrides = []): array
    {
        $payload = [
            'type' => 'variable',
            'slug' => null,
            'category_id' => null,
            'brand_id' => null,
            'currency_code' => null,
            'translations' => [
                'en' => ['name' => 'Variable Shirt'],
                'ar' => ['name' => ''],
            ],
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                    'sku' => 'VAR-RS',
                    'price' => '100',
                    'compare_at_price' => null,
                    'quantity' => 2,
                    'is_default' => true,
                ],
                [
                    'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                    'sku' => 'VAR-BM',
                    'price' => '120',
                    'compare_at_price' => '180',
                    'quantity' => 1,
                    'is_default' => false,
                ],
            ],
        ];

        foreach ($overrides as $key => $value) {
            $payload[$key] = $value;
        }

        return $payload;
    }

    public function test_existing_simple_products_keep_economics_and_default_variant_id(): void
    {
        $vendor = $this->createVendorUser();
        $product = app(ProductService::class)->createSimpleDraft($vendor->vendor->store, [
            'type' => 'simple',
            'sku' => 'KEEP-SIMPLE',
            'price' => '185000',
            'quantity' => 4,
            'translations' => [
                'en' => ['name' => 'Simple Keep'],
                'ar' => ['name' => ''],
            ],
        ]);

        $this->assertSame(ProductType::Simple, $product->type);
        $this->assertNotNull($product->default_variant_id);
        $this->assertSame($product->default_variant_id, $product->defaultVariant->id);
        $this->assertSame($product->id, $product->defaultVariant->product_id);
        $this->assertSame('KEEP-SIMPLE', $product->defaultVariant->sku);
        $this->assertSame(185000, $product->defaultVariant->price_amount_minor);
        $this->assertSame(4, $product->defaultVariant->quantity);
        $this->assertSame(ProductVariant::DEFAULT_COMBINATION_KEY, $product->defaultVariant->combination_key);
        $this->assertFalse(Schema::hasColumn('product_variants', 'is_default'));
        $this->assertTrue(Schema::hasColumn('products', 'default_variant_id'));
    }

    public function test_simple_create_and_update_still_work_with_default_variant_id(): void
    {
        $vendor = $this->createVendorUser();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), [
                'type' => 'simple',
                'sku' => 'HTTP-S1',
                'price' => '50',
                'quantity' => 3,
                'translations' => [
                    'en' => ['name' => 'Http Simple'],
                    'ar' => ['name' => ''],
                ],
            ])
            ->assertRedirect();

        $product = Product::query()->where('store_id', $vendor->vendor->store->id)->firstOrFail();

        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $product), [
                'type' => 'simple',
                'slug' => $product->slug,
                'sku' => 'HTTP-S1B',
                'price' => '75',
                'quantity' => 9,
                'currency_code' => $product->currency_code,
                'translations' => [
                    'en' => ['name' => 'Http Simple Updated'],
                    'ar' => ['name' => ''],
                ],
            ])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame($product->default_variant_id, $product->defaultVariant->id);
        $this->assertSame('HTTP-S1B', $product->defaultVariant->sku);
        $this->assertSame(75, $product->defaultVariant->price_amount_minor);
        $this->assertSame(9, $product->defaultVariant->quantity);
    }

    public function test_default_variant_composite_ownership_fk_rejects_cross_product(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('products')->where('id', $a->id)->update([
            'default_variant_id' => $b->default_variant_id,
        ]);
    }

    public function test_cross_product_variant_attribute_link_is_rejected(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $service = app(ProductService::class);
        $product = $service->createVariableDraft($vendor->vendor->store, $this->variablePayload($attrs));
        $other = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        $assignment = $product->productAttributes()->firstOrFail();
        $selected = $assignment->selectedValues()->firstOrFail();

        $this->expectException(QueryException::class);

        DB::table('product_variant_attribute_values')->insert([
            'variant_id' => $other->defaultVariant->id,
            'product_id' => $product->id,
            'product_attribute_id' => $assignment->id,
            'product_attribute_value_id' => $selected->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_value_attribute_mismatch_is_rejected_by_db(): void
    {
        $product = Product::factory()->create();
        $color = Attribute::factory()->create();
        $size = Attribute::factory()->create();
        $red = AttributeValue::factory()->for($color)->create();
        $assignment = ProductAttribute::factory()->create([
            'product_id' => $product->id,
            'attribute_id' => $size->id,
        ]);

        $this->expectException(QueryException::class);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'product_attribute_id' => $assignment->id,
            'attribute_id' => $size->id,
            'attribute_value_id' => $red->id,
        ]);
    }

    public function test_unassigned_values_cannot_be_linked(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $product = app(ProductService::class)->createVariableDraft(
            $vendor->vendor->store,
            $this->variablePayload($attrs, [
                'attributes' => [
                    ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id]],
                ],
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id],
                        'sku' => 'ONE-ATTR',
                        'price' => '10',
                        'quantity' => 1,
                        'is_default' => true,
                    ],
                ],
            ]),
        );

        $assignment = $product->productAttributes()->firstOrFail();
        $variant = $product->defaultVariant;
        $foreignSelected = ProductAttributeValue::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('product_variant_attribute_values')->insert([
            'variant_id' => $variant->id,
            'product_id' => $product->id,
            'product_attribute_id' => $assignment->id,
            'product_attribute_value_id' => $foreignSelected->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_at_most_one_value_per_assigned_attribute(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $product = app(ProductService::class)->createVariableDraft(
            $vendor->vendor->store,
            $this->variablePayload($attrs, [
                'attributes' => [
                    ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ],
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id],
                        'sku' => 'COLOR-RED',
                        'price' => '10',
                        'quantity' => 1,
                        'is_default' => true,
                    ],
                ],
            ]),
        );

        $assignment = $product->productAttributes()->firstOrFail();
        $blue = ProductAttributeValue::query()
            ->where('product_attribute_id', $assignment->id)
            ->where('attribute_value_id', $attrs['blue']->id)
            ->firstOrFail();

        $this->expectException(QueryException::class);

        DB::table('product_variant_attribute_values')->insert([
            'variant_id' => $product->default_variant_id,
            'product_id' => $product->id,
            'product_attribute_id' => $assignment->id,
            'product_attribute_value_id' => $blue->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_canonical_key_ignores_request_order_and_reserves_default(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $service = app(ProductService::class);

        $product = $service->createVariableDraft($vendor->vendor->store, $this->variablePayload($attrs, [
            'variants' => [
                [
                    'value_ids' => [$attrs['small']->id, $attrs['red']->id],
                    'sku' => 'ORDER-A',
                    'price' => '10',
                    'quantity' => 1,
                    'is_default' => true,
                ],
            ],
            'attributes' => [
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id]],
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id]],
            ],
        ]));

        $expected = CombinationKey::forVariable([
            $attrs['color']->id => $attrs['red']->id,
            $attrs['size']->id => $attrs['small']->id,
        ]);

        $this->assertSame($expected, $product->defaultVariant->combination_key);
        $this->assertNotSame(ProductVariant::DEFAULT_COMBINATION_KEY, $product->defaultVariant->combination_key);
        $this->assertSame(ProductVariant::DEFAULT_COMBINATION_KEY, CombinationKey::SIMPLE);
    }

    public function test_duplicate_combinations_are_rejected_and_reuse_restores_same_row(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $service = app(ProductService::class);
        $store = $vendor->vendor->store;

        try {
            $service->createVariableDraft($store, $this->variablePayload($attrs, [
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'DUP-1',
                        'price' => '10',
                        'quantity' => 1,
                        'is_default' => true,
                    ],
                    [
                        'value_ids' => [$attrs['small']->id, $attrs['red']->id],
                        'sku' => 'DUP-2',
                        'price' => '11',
                        'quantity' => 1,
                        'is_default' => false,
                    ],
                ],
            ]));
            $this->fail('Expected duplicate combination to fail.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $product = $service->createVariableDraft($store, $this->variablePayload($attrs));
        $originalId = $product->variants()->where('sku', 'VAR-BM')->value('id');

        $service->syncVariableMatrix($product, [
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                    'sku' => 'VAR-RS',
                    'price' => '100',
                    'quantity' => 2,
                    'is_default' => true,
                ],
            ],
        ]);

        $this->assertSoftDeleted('product_variants', ['id' => $originalId]);

        $restored = $service->syncVariableMatrix($product->fresh(), [
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                    'sku' => 'VAR-RS',
                    'price' => '100',
                    'quantity' => 2,
                    'is_default' => true,
                ],
                [
                    'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                    'sku' => 'VAR-BM',
                    'price' => '130',
                    'quantity' => 4,
                    'is_default' => false,
                ],
            ],
        ]);

        $this->assertSame($originalId, $restored->variants()->where('sku', 'VAR-BM')->value('id'));
        $this->assertSame(130, ProductVariant::query()->findOrFail($originalId)->price_amount_minor);
        $this->assertNull(ProductVariant::withTrashed()->findOrFail($originalId)->deleted_at);
    }

    public function test_duplicate_normalized_sku_is_rejected(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $service = app(ProductService::class);

        try {
            $service->createVariableDraft($vendor->vendor->store, $this->variablePayload($attrs, [
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'same-sku',
                        'price' => '10',
                        'quantity' => 1,
                        'is_default' => true,
                    ],
                    [
                        'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                        'sku' => 'SAME-SKU',
                        'price' => '11',
                        'quantity' => 1,
                        'is_default' => false,
                    ],
                ],
            ]));
            $this->fail('Expected duplicate SKU to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('variants.1.sku', $exception->errors());
        }

        $service->createSimpleDraft($vendor->vendor->store, [
            'sku' => 'TAKEN-SKU',
            'price' => '10',
            'quantity' => 1,
            'translations' => ['en' => ['name' => 'Taken']],
        ]);

        try {
            $service->createVariableDraft($vendor->vendor->store, $this->variablePayload($attrs, [
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'taken-sku',
                        'price' => '10',
                        'quantity' => 1,
                        'is_default' => true,
                    ],
                ],
                'attributes' => [
                    ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id]],
                    ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id]],
                ],
            ]));
            $this->fail('Expected store SKU collision to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sku', $exception->errors());
        }
    }

    public function test_money_and_quantity_boundaries_for_variable_variants(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $service = app(ProductService::class);
        $store = $vendor->vendor->store;

        foreach ([
            ['price' => '0'],
            ['price' => '10', 'compare_at_price' => '10'],
            ['quantity' => -1],
            ['quantity' => (string) (ProductVariant::MAX_QUANTITY + 1)],
        ] as $invalid) {
            try {
                $service->createVariableDraft($store, $this->variablePayload($attrs, [
                    'variants' => [[
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'BAD-'.uniqid(),
                        'price' => $invalid['price'] ?? '10',
                        'compare_at_price' => $invalid['compare_at_price'] ?? null,
                        'quantity' => $invalid['quantity'] ?? 1,
                        'is_default' => true,
                    ]],
                    'attributes' => [
                        ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id]],
                        ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id]],
                    ],
                ]));
                $this->fail('Expected economics validation to fail.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $product = $service->createVariableDraft($store, $this->variablePayload($attrs, [
            'currency_code' => 'USD',
            'variants' => [[
                'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                'sku' => 'USD-OK',
                'price' => '10.50',
                'compare_at_price' => '12.00',
                'quantity' => ProductVariant::MAX_QUANTITY,
                'is_default' => true,
            ]],
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id]],
            ],
        ]));

        $this->assertSame(1050, $product->defaultVariant->price_amount_minor);
        $this->assertSame(1200, $product->defaultVariant->compare_at_amount_minor);
        $this->assertSame(ProductVariant::MAX_QUANTITY, $product->defaultVariant->quantity);
        $this->assertFalse(Schema::hasColumn('products', 'price_amount_minor'));
        $this->assertFalse(Schema::hasColumn('products', 'sku'));
        $this->assertFalse(Schema::hasColumn('products', 'quantity'));
    }

    public function test_inactive_attribute_and_value_cannot_be_newly_assigned_or_restored_live(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $service = app(ProductService::class);
        $store = $vendor->vendor->store;

        $attrs['color']->update(['is_active' => false]);

        try {
            $service->createVariableDraft($store, $this->variablePayload($attrs));
            $this->fail('Expected inactive attribute assignment to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attributes', $exception->errors());
        }

        $attrs['color']->update(['is_active' => true]);
        $attrs['blue']->update(['is_active' => false]);

        try {
            $service->createVariableDraft($store, $this->variablePayload($attrs));
            $this->fail('Expected inactive value selection to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attributes', $exception->errors());
        }

        $attrs['blue']->update(['is_active' => true]);
        $product = $service->createVariableDraft($store, $this->variablePayload($attrs));

        $service->syncVariableMatrix($product, [
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                    'sku' => 'VAR-RS',
                    'price' => '100',
                    'quantity' => 2,
                    'is_default' => true,
                ],
            ],
        ]);

        $attrs['blue']->update(['is_active' => false]);

        try {
            $service->syncVariableMatrix($product->fresh(), [
                'attributes' => [
                    ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                    ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
                ],
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'VAR-RS',
                        'price' => '100',
                        'quantity' => 2,
                        'is_default' => true,
                    ],
                    [
                        'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                        'sku' => 'VAR-BM',
                        'price' => '120',
                        'quantity' => 1,
                        'is_default' => false,
                    ],
                ],
            ]);
            $this->fail('Expected inactive value restore to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('variants', $exception->errors());
        }
    }

    public function test_attribute_value_and_cartesian_limits(): void
    {
        $vendor = $this->createVendorUser();
        $service = app(ProductService::class);
        $store = $vendor->vendor->store;

        $four = collect(range(1, 4))->map(fn () => Attribute::factory()->create());
        $values = $four->mapWithKeys(fn (Attribute $attribute) => [
            $attribute->id => AttributeValue::factory()->for($attribute)->create(),
        ]);

        try {
            $service->createVariableDraft($store, [
                'type' => 'variable',
                'translations' => ['en' => ['name' => 'Too many attributes']],
                'attributes' => $four->map(fn (Attribute $attribute) => [
                    'attribute_id' => $attribute->id,
                    'value_ids' => [$values[$attribute->id]->id],
                ])->all(),
                'variants' => [[
                    'value_ids' => $values->pluck('id')->all(),
                    'sku' => 'TOO-ATTR',
                    'price' => '10',
                    'quantity' => 1,
                    'is_default' => true,
                ]],
            ]);
            $this->fail('Expected max attribute limit to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attributes', $exception->errors());
        }

        $color = Attribute::factory()->create();
        $nine = AttributeValue::factory()->for($color)->count(9)->create();

        try {
            $service->createVariableDraft($store, [
                'type' => 'variable',
                'translations' => ['en' => ['name' => 'Too many values']],
                'attributes' => [[
                    'attribute_id' => $color->id,
                    'value_ids' => $nine->pluck('id')->all(),
                ]],
                'variants' => [[
                    'value_ids' => [$nine->first()->id],
                    'sku' => 'TOO-VAL',
                    'price' => '10',
                    'quantity' => 1,
                    'is_default' => true,
                ]],
            ]);
            $this->fail('Expected max values limit to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attributes.0.value_ids', $exception->errors());
        }

        $left = Attribute::factory()->create();
        $right = Attribute::factory()->create();
        $leftValues = AttributeValue::factory()->for($left)->count(8)->create();
        $rightValues = AttributeValue::factory()->for($right)->count(7)->create();
        $before = Product::query()->count();

        try {
            $service->createVariableDraft($store, [
                'type' => 'variable',
                'translations' => ['en' => ['name' => 'Cartesian overflow']],
                'attributes' => [
                    ['attribute_id' => $left->id, 'value_ids' => $leftValues->pluck('id')->all()],
                    ['attribute_id' => $right->id, 'value_ids' => $rightValues->pluck('id')->all()],
                ],
                'variants' => [[
                    'value_ids' => [$leftValues->first()->id, $rightValues->first()->id],
                    'sku' => 'CART-OVER',
                    'price' => '10',
                    'quantity' => 1,
                    'is_default' => true,
                ]],
            ]);
            $this->fail('Expected cartesian limit to fail before write.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attributes', $exception->errors());
        }

        $this->assertSame($before, Product::query()->count());
    }

    public function test_incomplete_combinations_and_missing_default_are_rejected(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $service = app(ProductService::class);
        $store = $vendor->vendor->store;

        try {
            $service->createVariableDraft($store, $this->variablePayload($attrs, [
                'variants' => [[
                    'value_ids' => [$attrs['red']->id],
                    'sku' => 'INCOMPLETE',
                    'price' => '10',
                    'quantity' => 1,
                    'is_default' => true,
                ]],
            ]));
            $this->fail('Expected incomplete combination to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('variants.0.value_ids', $exception->errors());
        }

        try {
            $service->createVariableDraft($store, $this->variablePayload($attrs, [
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'NO-DEF',
                        'price' => '10',
                        'quantity' => 1,
                        'is_default' => false,
                    ],
                ],
            ]));
            $this->fail('Expected missing default to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('default_variant', $exception->errors());
        }

        try {
            $service->createVariableDraft($store, $this->variablePayload($attrs, [
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'DEF-A',
                        'price' => '10',
                        'quantity' => 1,
                        'is_default' => true,
                    ],
                    [
                        'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                        'sku' => 'DEF-B',
                        'price' => '11',
                        'quantity' => 1,
                        'is_default' => true,
                    ],
                ],
            ]));
            $this->fail('Expected two defaults to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('default_variant', $exception->errors());
        }
    }

    public function test_invalid_variant_rolls_back_the_entire_write(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $beforeProducts = Product::query()->count();
        $beforeVariants = ProductVariant::query()->count();

        try {
            app(ProductService::class)->createVariableDraft($vendor->vendor->store, $this->variablePayload($attrs, [
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'OK-ONE',
                        'price' => '10',
                        'quantity' => 1,
                        'is_default' => true,
                    ],
                    [
                        'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                        'sku' => 'BAD-TWO',
                        'price' => '0',
                        'quantity' => 1,
                        'is_default' => false,
                    ],
                ],
            ]));
            $this->fail('Expected invalid variant to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('variants.1.price', $exception->errors());
        }

        $this->assertSame($beforeProducts, Product::query()->count());
        $this->assertSame($beforeVariants, ProductVariant::query()->count());
        $this->assertSame(0, ProductAttribute::query()->count());
    }

    public function test_product_type_is_immutable(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $service = app(ProductService::class);
        $store = $vendor->vendor->store;

        $simple = $service->createSimpleDraft($store, [
            'sku' => 'IMM-SIMPLE',
            'price' => '10',
            'quantity' => 1,
            'translations' => ['en' => ['name' => 'Immutable Simple']],
        ]);

        try {
            $service->updateSimpleDraft($simple, [
                'type' => 'variable',
                'sku' => 'IMM-SIMPLE',
                'price' => '10',
                'quantity' => 1,
                'translations' => ['en' => ['name' => 'Immutable Simple']],
            ]);
            $this->fail('Expected simple type change to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }

        try {
            $service->createVariableDraft($store, $this->variablePayload($attrs, ['type' => 'simple']));
            $this->fail('Expected variable path to reject simple type.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }

        $variable = $service->createVariableDraft($store, $this->variablePayload($attrs, [
            'translations' => ['en' => ['name' => 'Immutable Variable']],
        ]));

        try {
            $service->updateSimpleDraft($variable, [
                'type' => 'simple',
                'sku' => 'IMM-VAR',
                'price' => '10',
                'quantity' => 1,
                'translations' => ['en' => ['name' => 'Converted']],
            ]);
            $this->fail('Expected variable product to reject simple update path.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }

        $this->assertSame(ProductType::Simple, $simple->fresh()->type);
        $this->assertSame(ProductType::Variable, $variable->fresh()->type);
    }

    public function test_draft_pre_publication_matrix_synchronization(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $service = app(ProductService::class);
        $product = $service->createVariableDraft($vendor->vendor->store, $this->variablePayload($attrs));

        $green = AttributeValue::factory()->for($attrs['color'])->create(['code' => 'green-'.uniqid()]);
        $synced = $service->syncVariableMatrix($product, [
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $green->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['red']->id],
                    'sku' => 'COLOR-RED',
                    'price' => '15',
                    'quantity' => 3,
                    'is_default' => true,
                ],
                [
                    'value_ids' => [$green->id],
                    'sku' => 'COLOR-GREEN',
                    'price' => '16',
                    'quantity' => 2,
                    'is_default' => false,
                ],
            ],
        ]);

        $this->assertSame(1, $synced->productAttributes()->count());
        $this->assertSame(2, $synced->productAttributeValues()->count());
        $this->assertSame(2, $synced->variants()->count());
        $this->assertTrue(ProductVariant::withTrashed()->where('product_id', $synced->id)->where('sku', 'VAR-RS')->exists());
        $this->assertNotNull(ProductVariant::withTrashed()->where('sku', 'VAR-RS')->first()?->deleted_at);
        $this->assertNotSame(ProductVariant::DEFAULT_COMBINATION_KEY, $synced->defaultVariant->combination_key);
    }

    public function test_historical_links_survive_variant_archive_and_default_is_reassigned_atomically(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $service = app(ProductService::class);
        $product = $service->createVariableDraft($vendor->vendor->store, $this->variablePayload($attrs));

        $archivedSku = 'VAR-BM';
        $archived = $product->variants()->where('sku', $archivedSku)->firstOrFail();
        $linkCount = ProductVariantAttributeValue::query()->where('variant_id', $archived->id)->count();
        $this->assertSame(2, $linkCount);

        $updated = $service->syncVariableMatrix($product, [
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                    'sku' => 'VAR-RS',
                    'price' => '100',
                    'quantity' => 2,
                    'is_default' => false,
                ],
                [
                    'value_ids' => [$attrs['blue']->id, $attrs['small']->id],
                    'sku' => 'VAR-BS',
                    'price' => '110',
                    'quantity' => 5,
                    'is_default' => true,
                ],
            ],
        ]);

        $this->assertSoftDeleted('product_variants', ['id' => $archived->id]);
        $this->assertSame($linkCount, ProductVariantAttributeValue::query()->where('variant_id', $archived->id)->count());
        $this->assertSame('VAR-BS', $updated->defaultVariant->sku);
        $this->assertSame($updated->default_variant_id, $updated->defaultVariant->id);
        $this->assertNotSame($archived->id, $updated->default_variant_id);
        $this->assertSame(2, $updated->variants()->count());
    }

    public function test_last_live_variant_cannot_be_archived_and_empty_matrix_is_rejected(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $service = app(ProductService::class);
        $product = $service->createVariableDraft($vendor->vendor->store, $this->variablePayload($attrs, [
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id]],
            ],
            'variants' => [[
                'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                'sku' => 'ONLY-ONE',
                'price' => '10',
                'quantity' => 1,
                'is_default' => true,
            ]],
        ]));

        try {
            $service->syncVariableMatrix($product, [
                'attributes' => [
                    ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id]],
                    ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id]],
                ],
                'variants' => [],
            ]);
            $this->fail('Expected empty matrix to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('variants', $exception->errors());
        }

        $this->assertSame(1, $product->fresh()->variants()->count());
        $this->assertNotNull($product->fresh()->default_variant_id);
    }

    public function test_post_publication_topology_is_frozen_while_economics_remain_editable(): void
    {
        $attrs = $this->colorAndSize();
        $vendor = $this->createVendorUser();
        $service = app(ProductService::class);
        $product = $service->createVariableDraft($vendor->vendor->store, $this->variablePayload($attrs));
        $this->actingAs($vendor);
        $product = $this->preparePublishedIntegrity($product);

        $product->forceFill([
            'status' => ProductStatus::Published,
            'published_at' => now(),
        ])->save();

        $green = AttributeValue::factory()->for($attrs['color'])->create();

        try {
            $service->syncVariableMatrix($product->fresh(), [
                'attributes' => [
                    ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id, $green->id]],
                    ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
                ],
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'VAR-RS',
                        'price' => '100',
                        'quantity' => 2,
                        'is_default' => true,
                    ],
                ],
            ]);
            $this->fail('Expected topology freeze to reject new values.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attributes', $exception->errors());
        }

        try {
            $service->syncVariableMatrix($product->fresh(), [
                'attributes' => [
                    ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                    ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
                ],
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'VAR-RS',
                        'price' => '100',
                        'quantity' => 2,
                        'is_default' => true,
                    ],
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['medium']->id],
                        'sku' => 'NEW-COMBO',
                        'price' => '140',
                        'quantity' => 1,
                        'is_default' => false,
                    ],
                ],
            ]);
            $this->fail('Expected topology freeze to reject new combinations.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('variants', $exception->errors());
        }

        $updated = $service->syncVariableMatrix($product->fresh(), [
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                    'sku' => 'VAR-RS-EDIT',
                    'price' => '200',
                    'compare_at_price' => '250',
                    'quantity' => 8,
                    'is_default' => false,
                ],
                [
                    'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                    'sku' => 'VAR-BM',
                    'price' => '120',
                    'quantity' => 1,
                    'is_default' => true,
                ],
            ],
        ]);

        $this->assertSame('VAR-RS-EDIT', $updated->variants()->where('combination_key', CombinationKey::forVariable([
            $attrs['color']->id => $attrs['red']->id,
            $attrs['size']->id => $attrs['small']->id,
        ]))->value('sku'));
        $this->assertSame(200, $updated->variants()->where('sku', 'VAR-RS-EDIT')->value('price_amount_minor'));
        $this->assertSame('VAR-BM', $updated->defaultVariant->sku);
        $this->assertSame(2, $updated->productAttributes()->count());

        $afterArchive = $service->syncVariableMatrix($updated, [
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                    'sku' => 'VAR-BM',
                    'price' => '120',
                    'quantity' => 1,
                    'is_default' => true,
                ],
            ],
        ]);

        $this->assertSame(1, $afterArchive->variants()->count());
        $this->assertSoftDeleted('product_variants', ['sku' => 'VAR-RS-EDIT']);
        $this->assertSame(
            2,
            ProductVariantAttributeValue::query()
                ->where('variant_id', ProductVariant::withTrashed()->where('sku', 'VAR-RS-EDIT')->value('id'))
                ->count(),
        );
    }

    public function test_authorization_is_unchanged_and_variable_without_matrix_is_rejected(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $vendor = $this->createVendorUser();
        $other = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        $this->assertFalse($admin->can('create', Product::class));
        $this->assertFalse($customer->can('create', Product::class));
        $this->assertFalse($other->can('update', $product));

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), [
                'type' => 'variable',
                'sku' => 'HTTP-VAR',
                'price' => '10',
                'quantity' => 1,
                'translations' => [
                    'en' => ['name' => 'Http Variable'],
                    'ar' => ['name' => ''],
                ],
            ])
            ->assertSessionHasErrors('attributes');

        $this->assertFalse(Route::has('vendor.products.matrix'));
        $this->assertFalse(Route::has('vendor.products.variable'));
        $this->assertFalse(Route::has('vendor.products.destroy'));
    }
}
