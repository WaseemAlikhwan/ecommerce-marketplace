<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use App\Models\User;
use App\Services\ProductService;
use App\Support\VendorProductFormState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class VendorVariableProductHttpTest extends TestCase
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
        $color = Attribute::factory()->create(['code' => 'color-http-'.uniqid()]);
        $size = Attribute::factory()->create(['code' => 'size-http-'.uniqid()]);

        return [
            'color' => $color,
            'size' => $size,
            'red' => AttributeValue::factory()->for($color)->create(['code' => 'red-http']),
            'blue' => AttributeValue::factory()->for($color)->create(['code' => 'blue-http']),
            'small' => AttributeValue::factory()->for($size)->create(['code' => 's-http']),
            'medium' => AttributeValue::factory()->for($size)->create(['code' => 'm-http']),
        ];
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function variableForm(array $attrs, array $overrides = []): array
    {
        $payload = [
            'type' => 'variable',
            'slug' => null,
            'category_id' => null,
            'brand_id' => null,
            'currency_code' => 'SYP',
            'translations' => [
                'en' => ['name' => 'Http Variable Shirt', 'short_description' => null, 'description' => null],
                'ar' => ['name' => '', 'short_description' => null, 'description' => null],
            ],
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                    'sku' => 'HTTP-RS',
                    'price' => '100',
                    'compare_at_price' => null,
                    'quantity' => 2,
                    'is_default' => 1,
                ],
                [
                    'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                    'sku' => 'HTTP-BM',
                    'price' => '150',
                    'compare_at_price' => '200',
                    'quantity' => 1,
                    'is_default' => 0,
                ],
            ],
        ];

        foreach ($overrides as $key => $value) {
            $payload[$key] = $value;
        }

        return $payload;
    }

    public function test_guest_is_redirected_and_customer_admin_are_forbidden(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $vendor = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        $this->get(route('vendor.products.create'))->assertRedirect('/login');
        $this->post(route('vendor.products.store'), ['type' => 'variable'])->assertRedirect('/login');

        $this->actingAs($customer)->get(route('vendor.products.create'))->assertForbidden();
        $this->actingAs($customer)->post(route('vendor.products.store'), ['type' => 'variable'])->assertForbidden();
        $this->actingAs($admin)->get(route('vendor.products.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('vendor.products.store'), ['type' => 'variable'])->assertForbidden();
        $this->assertFalse($admin->can('create', Product::class));
        $this->assertFalse($customer->can('update', $product));
    }

    public function test_vendor_can_manage_only_own_store_products(): void
    {
        $owner = $this->createVendorUser();
        $other = $this->createVendorUser();
        $attrs = $this->colorAndSize();

        $this->actingAs($owner)
            ->post(route('vendor.products.store'), $this->variableForm($attrs))
            ->assertRedirect();

        $product = Product::query()->where('store_id', $owner->vendor->store->id)->firstOrFail();

        $this->actingAs($other)->get(route('vendor.products.edit', $product))->assertForbidden();
        $this->actingAs($other)->put(route('vendor.products.update', $product), $this->variableForm($attrs, [
            'slug' => $product->slug,
        ]))->assertForbidden();
    }

    public function test_create_page_exposes_active_localized_dictionary_and_hides_inactive_globals(): void
    {
        $vendor = $this->createVendorUser();
        $active = Attribute::factory()->create(['code' => 'color-active']);
        AttributeValue::factory()->for($active)->create(['code' => 'red-active']);
        $inactive = Attribute::factory()->inactive()->create(['code' => 'size-inactive']);
        AttributeValue::factory()->for($inactive)->create(['code' => 'xl-inactive']);

        $this->actingAs($vendor)
            ->get(route('vendor.products.create'))
            ->assertOk()
            ->assertSee(__('Simple product'), false)
            ->assertSee(__('Variable product'), false)
            ->assertSee($active->name('en'), false)
            ->assertDontSee('size-inactive', false)
            ->assertDontSee('xl-inactive', false)
            ->assertDontSee('Variable products come later', false);
    }

    public function test_valid_variable_post_creates_assignments_variants_and_default(): void
    {
        $vendor = $this->createVendorUser();
        $attrs = $this->colorAndSize();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs))
            ->assertRedirect();

        $product = Product::query()->where('store_id', $vendor->vendor->store->id)->firstOrFail();
        $this->assertSame(ProductType::Variable, $product->type);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame(2, $product->productAttributes()->count());
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame('HTTP-RS', $product->defaultVariant->sku);
        $this->assertSame($product->default_variant_id, $product->defaultVariant->id);
        $this->assertSame(2, ProductVariantAttributeValue::query()->where('variant_id', $product->default_variant_id)->count());
    }

    public function test_simple_product_post_remains_unchanged(): void
    {
        $vendor = $this->createVendorUser();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), [
                'type' => 'simple',
                'sku' => 'HTTP-SIMPLE',
                'price' => '80',
                'quantity' => 4,
                'translations' => [
                    'en' => ['name' => 'Http Simple'],
                    'ar' => ['name' => ''],
                ],
            ])
            ->assertRedirect();

        $product = Product::query()->where('store_id', $vendor->vendor->store->id)->firstOrFail();
        $this->assertSame(ProductType::Simple, $product->type);
        $this->assertSame('HTTP-SIMPLE', $product->defaultVariant->sku);
        $this->assertSame(0, $product->productAttributes()->count());
    }

    public function test_conditional_validation_and_forged_type_conversion_are_rejected(): void
    {
        $vendor = $this->createVendorUser();
        $attrs = $this->colorAndSize();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), [
                'type' => 'simple',
                'translations' => ['en' => ['name' => 'Needs Sku']],
            ])
            ->assertSessionHasErrors(['sku', 'price', 'quantity']);

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), [
                'type' => 'variable',
                'translations' => ['en' => ['name' => 'Needs Matrix']],
            ])
            ->assertSessionHasErrors(['attributes', 'variants']);

        $simple = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);
        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $simple), [
                'type' => 'variable',
                'slug' => $simple->slug,
                'currency_code' => $simple->currency_code,
                'sku' => $simple->defaultVariant->sku,
                'price' => '10',
                'quantity' => 1,
                'translations' => ['en' => ['name' => 'Forged']],
            ])
            ->assertSessionHasErrors('type');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs, [
                'translations' => ['en' => ['name' => 'Keep Variable']],
            ]))
            ->assertRedirect();

        $variable = Product::query()->where('type', ProductType::Variable->value)->firstOrFail();
        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $variable), [
                'type' => 'simple',
                'slug' => $variable->slug,
                'currency_code' => $variable->currency_code,
                'sku' => 'NOPE',
                'price' => '10',
                'quantity' => 1,
                'translations' => ['en' => ['name' => 'Forged Simple']],
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_malformed_ids_inactive_assignment_limits_default_and_sku_rules(): void
    {
        $vendor = $this->createVendorUser();
        $attrs = $this->colorAndSize();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs, [
                'attributes' => [
                    ['attribute_id' => 999999, 'value_ids' => [$attrs['red']->id]],
                ],
                'variants' => [[
                    'value_ids' => [$attrs['red']->id],
                    'sku' => 'BAD-ID',
                    'price' => '10',
                    'quantity' => 1,
                    'is_default' => 1,
                ]],
            ]))
            ->assertSessionHasErrors();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs, [
                'attributes' => [
                    ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['small']->id]],
                ],
                'variants' => [[
                    'value_ids' => [$attrs['small']->id],
                    'sku' => 'MISMATCH',
                    'price' => '10',
                    'quantity' => 1,
                    'is_default' => 1,
                ]],
            ]))
            ->assertSessionHasErrors();

        $attrs['color']->update(['is_active' => false]);
        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs))
            ->assertSessionHasErrors();
        $attrs['color']->update(['is_active' => true]);

        $fourth = Attribute::factory()->count(4)->create();
        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs, [
                'attributes' => $fourth->map(fn (Attribute $attribute) => [
                    'attribute_id' => $attribute->id,
                    'value_ids' => [AttributeValue::factory()->for($attribute)->create()->id],
                ])->all(),
                'variants' => [[
                    'value_ids' => $fourth->map(fn (Attribute $attribute) => $attribute->values()->value('id'))->all(),
                    'sku' => 'TOO-MANY-ATTR',
                    'price' => '10',
                    'quantity' => 1,
                    'is_default' => 1,
                ]],
            ]))
            ->assertSessionHasErrors('attributes');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs, [
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'NO-DEF',
                        'price' => '10',
                        'quantity' => 1,
                        'is_default' => 0,
                    ],
                ],
            ]))
            ->assertSessionHasErrors('default_variant');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs, [
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'same-sku',
                        'price' => '10',
                        'quantity' => 1,
                        'is_default' => 1,
                    ],
                    [
                        'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                        'sku' => 'SAME-SKU',
                        'price' => '11',
                        'quantity' => 1,
                        'is_default' => 0,
                    ],
                ],
            ]))
            ->assertSessionHasErrors();
    }

    public function test_invalid_variant_rolls_back_product_creation(): void
    {
        $vendor = $this->createVendorUser();
        $attrs = $this->colorAndSize();
        $before = Product::query()->count();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs, [
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'OK-ROW',
                        'price' => '10',
                        'quantity' => 1,
                        'is_default' => 1,
                    ],
                    [
                        'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                        'sku' => 'BAD-ROW',
                        'price' => '0',
                        'quantity' => 1,
                        'is_default' => 0,
                    ],
                ],
            ]))
            ->assertSessionHasErrors();

        $this->assertSame($before, Product::query()->count());
    }

    public function test_edit_shows_combinations_and_formatted_economics(): void
    {
        $vendor = $this->createVendorUser();
        $attrs = $this->colorAndSize();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs, [
                'currency_code' => 'USD',
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'USD-RS',
                        'price' => '10.50',
                        'compare_at_price' => '12.00',
                        'quantity' => 3,
                        'is_default' => 1,
                    ],
                ],
                'attributes' => [
                    ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id]],
                    ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id]],
                ],
            ]))
            ->assertRedirect();

        $product = Product::query()->latest('id')->firstOrFail();

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product))
            ->assertOk()
            ->assertSee('USD-RS', false)
            ->assertSee('10.50', false)
            ->assertSee('12.00', false)
            ->assertSee($attrs['color']->name(), false)
            ->assertSee(__('Product type is locked after creation.'), false);
    }

    public function test_variable_metadata_and_matrix_update_is_atomic_and_validation_preserves_state(): void
    {
        $vendor = $this->createVendorUser();
        $attrs = $this->colorAndSize();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs, [
                'translations' => ['en' => ['name' => 'Original Name']],
            ]))
            ->assertRedirect();

        $product = Product::query()->latest('id')->firstOrFail();
        $originalSlug = $product->slug;

        $this->actingAs($vendor)
            ->from(route('vendor.products.edit', $product))
            ->put(route('vendor.products.update', $product), $this->variableForm($attrs, [
                'slug' => 'changed-slug',
                'translations' => ['en' => ['name' => 'Changed Name']],
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'HTTP-RS',
                        'price' => '0',
                        'quantity' => 2,
                        'is_default' => 1,
                    ],
                ],
            ]))
            ->assertRedirect(route('vendor.products.edit', $product))
            ->assertSessionHasErrors();

        $product->refresh();
        $this->assertSame($originalSlug, $product->slug);
        $this->assertSame('Original Name', $product->name('en'));
        $this->assertSame(2, $product->variants()->count());

        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $product), $this->variableForm($attrs, [
                'slug' => 'updated-variable',
                'translations' => ['en' => ['name' => 'Updated Variable']],
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'HTTP-RS-2',
                        'price' => '180',
                        'quantity' => 5,
                        'is_default' => 1,
                    ],
                    [
                        'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                        'sku' => 'HTTP-BM',
                        'price' => '150',
                        'quantity' => 1,
                        'is_default' => 0,
                    ],
                ],
            ]))
            ->assertRedirect(route('vendor.products.edit', $product));

        $product->refresh();
        $this->assertSame('updated-variable', $product->slug);
        $this->assertSame('Updated Variable', $product->name('en'));
        $this->assertSame('HTTP-RS-2', $product->defaultVariant->sku);
        $this->assertSame(180, $product->defaultVariant->price_amount_minor);
    }

    public function test_draft_topology_archive_restore_and_historical_links(): void
    {
        $vendor = $this->createVendorUser();
        $attrs = $this->colorAndSize();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs))
            ->assertRedirect();

        $product = Product::query()->latest('id')->firstOrFail();
        $archived = $product->variants()->where('sku', 'HTTP-BM')->firstOrFail();
        $linkCount = ProductVariantAttributeValue::query()->where('variant_id', $archived->id)->count();

        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $product), $this->variableForm($attrs, [
                'slug' => $product->slug,
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'HTTP-RS',
                        'price' => '100',
                        'quantity' => 2,
                        'is_default' => 1,
                    ],
                ],
            ]))
            ->assertRedirect();

        $this->assertSoftDeleted('product_variants', ['id' => $archived->id]);
        $this->assertSame($linkCount, ProductVariantAttributeValue::query()->where('variant_id', $archived->id)->count());

        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $product), $this->variableForm($attrs, [
                'slug' => $product->slug,
            ]))
            ->assertRedirect();

        $this->assertNull(ProductVariant::withTrashed()->findOrFail($archived->id)->deleted_at);
        $this->assertSame($archived->id, ProductVariant::query()->where('sku', 'HTTP-BM')->value('id'));
    }

    public function test_post_publication_freeze_and_economics_default_edit(): void
    {
        $vendor = $this->createVendorUser();
        $attrs = $this->colorAndSize();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs))
            ->assertRedirect();

        $product = Product::query()->latest('id')->firstOrFail();
        $product = $this->preparePublishedIntegrity($product);
        $product->forceFill([
            'status' => ProductStatus::Published,
            'published_at' => now(),
        ])->save();

        $green = AttributeValue::factory()->for($attrs['color'])->create(['code' => 'green-new']);
        $publishedForm = [
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'translations' => [
                'en' => ['name' => 'Http Variable Shirt', 'short_description' => null, 'description' => null],
                'ar' => ['name' => 'قميص متغير', 'short_description' => null, 'description' => null],
            ],
        ];

        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $product), $this->variableForm($attrs, [
                ...$publishedForm,
                'attributes' => [
                    ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id, $green->id]],
                    ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
                ],
            ]))
            ->assertSessionHasErrors();

        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $product), $this->variableForm($attrs, [
                ...$publishedForm,
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                        'sku' => 'HTTP-RS-EDIT',
                        'price' => '220',
                        'quantity' => 9,
                        'is_default' => 0,
                    ],
                    [
                        'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                        'sku' => 'HTTP-BM',
                        'price' => '150',
                        'quantity' => 1,
                        'is_default' => 1,
                    ],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('HTTP-BM', $product->defaultVariant->sku);
        $this->assertSame(220, $product->variants()->where('sku', 'HTTP-RS-EDIT')->value('price_amount_minor'));
        $this->assertSame(2, $product->productAttributes()->count());
    }

    public function test_inactive_existing_assignment_stays_visible_and_inactive_archive_cannot_restore(): void
    {
        $vendor = $this->createVendorUser();
        $attrs = $this->colorAndSize();
        $service = app(ProductService::class);

        $product = $service->createVariableDraft($vendor->vendor->store, [
            'type' => 'variable',
            'translations' => ['en' => ['name' => 'Inactive Globals']],
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                    'sku' => 'LIVE-RS',
                    'price' => '100',
                    'quantity' => 1,
                    'is_default' => true,
                ],
                [
                    'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                    'sku' => 'ARCH-BM',
                    'price' => '120',
                    'quantity' => 1,
                    'is_default' => false,
                ],
            ],
        ]);

        $service->syncVariableMatrix($product, [
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                    'sku' => 'LIVE-RS',
                    'price' => '100',
                    'quantity' => 1,
                    'is_default' => true,
                ],
            ],
        ]);

        $attrs['blue']->update(['is_active' => false]);

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product->fresh()))
            ->assertOk()
            ->assertSee($attrs['color']->name(), false)
            ->assertSee($attrs['blue']->name(), false);

        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $product->fresh()), $this->variableForm($attrs, [
                'slug' => $product->slug,
                'translations' => ['en' => ['name' => 'Inactive Globals']],
            ]))
            ->assertSessionHasErrors();
    }

    public function test_last_live_variant_cannot_be_removed_and_index_shows_type_count_and_price_range(): void
    {
        $vendor = $this->createVendorUser();
        $attrs = $this->colorAndSize();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->variableForm($attrs, [
                'attributes' => [
                    ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id]],
                    ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id]],
                ],
                'variants' => [[
                    'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                    'sku' => 'ONLY-LIVE',
                    'price' => '90',
                    'quantity' => 1,
                    'is_default' => 1,
                ]],
                'translations' => ['en' => ['name' => 'Only Live']],
            ]))
            ->assertRedirect();

        $product = Product::query()->latest('id')->firstOrFail();

        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $product), $this->variableForm($attrs, [
                'slug' => $product->slug,
                'attributes' => [
                    ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id]],
                    ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id]],
                ],
                'variants' => [],
            ]))
            ->assertSessionHasErrors('variants');

        $this->actingAs($vendor)
            ->get(route('vendor.products'))
            ->assertOk()
            ->assertSee(__('Variable'), false)
            ->assertSee('ONLY-LIVE', false)
            ->assertSee('Only Live', false)
            ->assertSee('90', false);

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), [
                'type' => 'simple',
                'sku' => 'IDX-SIMPLE',
                'price' => '40',
                'quantity' => 2,
                'translations' => ['en' => ['name' => 'Index Simple']],
            ])
            ->assertRedirect();

        $this->actingAs($vendor)
            ->get(route('vendor.products'))
            ->assertOk()
            ->assertSee(__('Simple'), false)
            ->assertSee('IDX-SIMPLE', false)
            ->assertSee('Index Simple', false)
            ->assertDontSee(__('Create and manage simple product drafts for your store. Publishing comes later.'), false);
    }

    public function test_matrix_bootstrap_distinguishes_live_archived_and_reinclude_actions(): void
    {
        $vendor = $this->createVendorUser();
        $attrs = $this->colorAndSize();
        $service = app(ProductService::class);

        $product = $service->createVariableDraft($vendor->vendor->store, [
            'type' => 'variable',
            'translations' => ['en' => ['name' => 'Matrix UI State']],
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                    'sku' => 'LIVE-RS',
                    'price' => '100',
                    'quantity' => 1,
                    'is_default' => true,
                ],
                [
                    'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                    'sku' => 'ARCH-BM',
                    'price' => '120',
                    'quantity' => 1,
                    'is_default' => false,
                ],
            ],
        ]);

        $service->syncVariableMatrix($product, [
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                    'sku' => 'LIVE-RS',
                    'price' => '100',
                    'quantity' => 1,
                    'is_default' => true,
                ],
            ],
        ]);

        $bootstrap = VendorProductFormState::bootstrap($product->fresh(), 'SYP', true);
        $bySku = collect($bootstrap['rows'])->keyBy('sku');

        $live = $bySku['LIVE-RS'];
        $archived = $bySku['ARCH-BM'];

        $this->assertTrue($live['persisted']);
        $this->assertTrue($live['included']);
        $this->assertFalse($live['archived']);
        $this->assertFalse($live['canRestore']);
        $this->assertSame('undo_exclusion', VendorProductFormState::excludedRowAction([
            ...$live,
            'included' => false,
        ]));

        $this->assertTrue($archived['persisted']);
        $this->assertFalse($archived['included']);
        $this->assertTrue($archived['archived']);
        $this->assertTrue($archived['canRestore']);
        $this->assertSame('restore_archived', VendorProductFormState::excludedRowAction($archived));

        $this->assertSame('undo_exclusion', VendorProductFormState::excludedRowAction([
            'persisted' => false,
            'archived' => false,
            'canRestore' => false,
            'included' => false,
        ]));

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product))
            ->assertOk()
            ->assertSee(__('Undo exclusion'), false)
            ->assertSee(__('Restore archived combination'), false)
            ->assertSee(__('Excluded combinations'), false)
            ->assertSee(__('Temporarily excluded'), false)
            ->assertDontSee('(!frozen || row.archived)', false);

        $attrs['blue']->update(['is_active' => false]);
        $inactiveArchived = collect(VendorProductFormState::bootstrap($product->fresh(), 'SYP', true)['rows'])
            ->firstWhere('sku', 'ARCH-BM');

        $this->assertTrue($inactiveArchived['archived']);
        $this->assertFalse($inactiveArchived['canRestore']);
        $this->assertTrue($inactiveArchived['inactiveGlobals']);
        $this->assertSame('restore_blocked', VendorProductFormState::excludedRowAction($inactiveArchived));

        $liveWithInactiveValue = collect(VendorProductFormState::bootstrap($product->fresh(), 'SYP', true)['rows'])
            ->firstWhere('sku', 'LIVE-RS');
        $this->assertFalse($liveWithInactiveValue['archived']);
        $this->assertSame('undo_exclusion', VendorProductFormState::excludedRowAction([
            ...$liveWithInactiveValue,
            'included' => false,
        ]));

        $product = $this->preparePublishedIntegrity($product->fresh());
        $product->forceFill([
            'status' => ProductStatus::Published,
            'published_at' => now(),
        ])->save();

        $frozen = VendorProductFormState::bootstrap($product->fresh(), 'SYP', true);
        $this->assertTrue($frozen['frozen']);
        $frozenLive = collect($frozen['rows'])->firstWhere('sku', 'LIVE-RS');
        $this->assertFalse($frozenLive['archived']);
        $this->assertFalse($frozenLive['canRestore']);
        $this->assertSame('undo_exclusion', VendorProductFormState::excludedRowAction([
            ...$frozenLive,
            'included' => false,
        ]));

        $this->actingAs($vendor)
            ->withHeader('Accept-Language', 'en')
            ->get(route('vendor.products.edit', $product))
            ->assertOk()
            ->assertSee(__('Undo exclusion'), false)
            ->assertSee(__('Attribute topology is frozen'), false);
    }

    public function test_no_destroy_or_conversion_routes_exist(): void
    {
        $this->assertFalse(Route::has('vendor.products.destroy'));
        $this->assertFalse(Route::has('vendor.products.delete'));
        $this->assertFalse(Route::has('vendor.products.convert'));
        $this->assertFalse(Route::has('vendor.products.matrix'));
    }
}
