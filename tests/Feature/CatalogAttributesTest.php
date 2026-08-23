<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\User;
use App\Services\AttributeService;
use App\Services\AttributeValueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogAttributesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function attributePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'code' => null,
            'position' => 0,
            'is_active' => 1,
            'translations' => [
                'ar' => ['name' => 'لون'],
                'en' => ['name' => 'Color'],
            ],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function valuePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'code' => null,
            'position' => 0,
            'is_active' => 1,
            'translations' => [
                'ar' => ['name' => 'أحمر'],
                'en' => ['name' => 'Red'],
            ],
        ], $overrides);
    }

    public function test_guest_cannot_manage_attributes_or_values(): void
    {
        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create();

        $this->get(route('admin.attributes.index'))->assertRedirect('/login');
        $this->get(route('admin.attributes.create'))->assertRedirect('/login');
        $this->post(route('admin.attributes.store'), $this->attributePayload())->assertRedirect('/login');
        $this->get(route('admin.attributes.show', $attribute))->assertRedirect('/login');
        $this->get(route('admin.attribute-values.create', $attribute))->assertRedirect('/login');
        $this->post(route('admin.attribute-values.store', $attribute), $this->valuePayload())->assertRedirect('/login');
        $this->get(route('admin.attribute-values.edit', [$attribute, $value]))->assertRedirect('/login');
    }

    public function test_customer_cannot_manage_attributes_or_values(): void
    {
        $customer = User::factory()->create();
        $attribute = Attribute::factory()->create();

        $this->actingAs($customer)->get(route('admin.attributes.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.attributes.store'), $this->attributePayload())->assertForbidden();
        $this->actingAs($customer)->get(route('admin.attribute-values.create', $attribute))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.attribute-values.store', $attribute), $this->valuePayload())->assertForbidden();
    }

    public function test_vendor_cannot_manage_attributes_or_values(): void
    {
        $vendor = $this->createVendorUser();
        $attribute = Attribute::factory()->create();

        $this->actingAs($vendor)->get(route('admin.attributes.index'))->assertForbidden();
        $this->actingAs($vendor)->post(route('admin.attributes.store'), $this->attributePayload())->assertForbidden();
        $this->actingAs($vendor)->post(route('admin.attribute-values.store', $attribute), $this->valuePayload())->assertForbidden();
    }

    public function test_admin_can_create_attribute_with_arabic_and_english_names(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.attributes.store'), $this->attributePayload())
            ->assertRedirect();

        $attribute = Attribute::query()->firstOrFail();
        $this->assertSame('color', $attribute->code);
        $this->assertDatabaseHas('attribute_translations', [
            'attribute_id' => $attribute->id,
            'locale' => 'ar',
            'name' => 'لون',
        ]);
        $this->assertDatabaseHas('attribute_translations', [
            'attribute_id' => $attribute->id,
            'locale' => 'en',
            'name' => 'Color',
        ]);
    }

    public function test_super_admin_can_manage_attributes_and_values(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->post(route('admin.attributes.store'), $this->attributePayload([
                'translations' => [
                    'ar' => ['name' => 'مقاس'],
                    'en' => ['name' => 'Size'],
                ],
            ]))
            ->assertRedirect();

        $attribute = Attribute::query()->firstOrFail();
        $this->assertSame('size', $attribute->code);

        $this->actingAs($superAdmin)
            ->post(route('admin.attribute-values.store', $attribute), $this->valuePayload([
                'translations' => [
                    'ar' => ['name' => 'صغير'],
                    'en' => ['name' => 'Small'],
                ],
            ]))
            ->assertRedirect(route('admin.attributes.show', $attribute));

        $this->assertSame('small', $attribute->values()->firstOrFail()->code);
        $this->assertSame('Small', $attribute->values()->firstOrFail()->name('en'));
        $this->assertSame('صغير', $attribute->values()->firstOrFail()->name('ar'));
    }

    public function test_arabic_and_english_names_are_required(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.attributes.store'), $this->attributePayload([
                'translations' => [
                    'ar' => ['name' => ''],
                    'en' => ['name' => 'Color'],
                ],
            ]))
            ->assertSessionHasErrors('translations.ar.name');

        $this->actingAs($admin)
            ->post(route('admin.attributes.store'), $this->attributePayload([
                'translations' => [
                    'ar' => ['name' => 'لون'],
                    'en' => ['name' => ''],
                ],
            ]))
            ->assertSessionHasErrors('translations.en.name');

        $this->assertDatabaseCount('attributes', 0);
    }

    public function test_attribute_codes_are_generated_unique_and_stable_when_names_change(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.attributes.store'), $this->attributePayload())
            ->assertRedirect();

        $first = Attribute::query()->firstOrFail();
        $this->assertSame('color', $first->code);

        $this->actingAs($admin)
            ->post(route('admin.attributes.store'), $this->attributePayload([
                'translations' => [
                    'ar' => ['name' => 'لون ٢'],
                    'en' => ['name' => 'Color'],
                ],
            ]))
            ->assertRedirect();

        $second = Attribute::query()->where('id', '!=', $first->id)->firstOrFail();
        $this->assertSame('color-1', $second->code);

        $this->actingAs($admin)
            ->put(route('admin.attributes.update', $first), $this->attributePayload([
                'code' => $first->code,
                'translations' => [
                    'ar' => ['name' => 'صبغة'],
                    'en' => ['name' => 'Hue'],
                ],
            ]))
            ->assertRedirect();

        $this->assertSame('color', $first->fresh()->code);
        $this->assertSame('Hue', $first->fresh()->name('en'));
    }

    public function test_explicit_attribute_code_edit_enforces_uniqueness(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(AttributeService::class);

        $first = $service->create($this->attributePayload());
        $second = $service->create($this->attributePayload([
            'translations' => [
                'ar' => ['name' => 'مقاس'],
                'en' => ['name' => 'Size'],
            ],
        ]));

        $this->actingAs($admin)
            ->put(route('admin.attributes.update', $second), $this->attributePayload([
                'code' => $first->code,
                'translations' => [
                    'ar' => ['name' => 'مقاس'],
                    'en' => ['name' => 'Size'],
                ],
            ]))
            ->assertSessionHasErrors('code');
    }

    public function test_value_codes_are_unique_within_attribute_and_reusable_across_attributes(): void
    {
        $admin = User::factory()->admin()->create();
        $color = app(AttributeService::class)->create($this->attributePayload());
        $size = app(AttributeService::class)->create($this->attributePayload([
            'translations' => [
                'ar' => ['name' => 'مقاس'],
                'en' => ['name' => 'Size'],
            ],
        ]));

        $this->actingAs($admin)
            ->post(route('admin.attribute-values.store', $color), $this->valuePayload(['code' => 'red']))
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.attribute-values.store', $color), $this->valuePayload([
                'code' => 'red',
                'translations' => [
                    'ar' => ['name' => 'قرمزي'],
                    'en' => ['name' => 'Crimson'],
                ],
            ]))
            ->assertSessionHasErrors('code');

        $this->actingAs($admin)
            ->post(route('admin.attribute-values.store', $size), $this->valuePayload([
                'code' => 'red',
                'translations' => [
                    'ar' => ['name' => 'أحمر مقاس'],
                    'en' => ['name' => 'Red Size'],
                ],
            ]))
            ->assertRedirect();

        $this->assertSame(2, AttributeValue::query()->where('code', 'red')->count());
        $this->assertTrue($color->values()->where('code', 'red')->exists());
        $this->assertTrue($size->values()->where('code', 'red')->exists());
    }

    public function test_value_codes_remain_stable_when_names_change(): void
    {
        $admin = User::factory()->admin()->create();
        $attribute = app(AttributeService::class)->create($this->attributePayload());

        $this->actingAs($admin)
            ->post(route('admin.attribute-values.store', $attribute), $this->valuePayload())
            ->assertRedirect();

        $value = $attribute->values()->firstOrFail();
        $this->assertSame('red', $value->code);

        $this->actingAs($admin)
            ->put(route('admin.attribute-values.update', [$attribute, $value]), $this->valuePayload([
                'code' => 'red',
                'translations' => [
                    'ar' => ['name' => 'قرمزي'],
                    'en' => ['name' => 'Crimson'],
                ],
            ]))
            ->assertRedirect();

        $this->assertSame('red', $value->fresh()->code);
        $this->assertSame('Crimson', $value->fresh()->name('en'));
    }

    public function test_explicit_value_code_edit_enforces_uniqueness_within_attribute(): void
    {
        $admin = User::factory()->admin()->create();
        $attribute = app(AttributeService::class)->create($this->attributePayload());
        $values = app(AttributeValueService::class);

        $red = $values->create($attribute, $this->valuePayload());
        $black = $values->create($attribute, $this->valuePayload([
            'translations' => [
                'ar' => ['name' => 'أسود'],
                'en' => ['name' => 'Black'],
            ],
        ]));

        $this->actingAs($admin)
            ->put(route('admin.attribute-values.update', [$attribute, $black]), $this->valuePayload([
                'code' => $red->code,
                'translations' => [
                    'ar' => ['name' => 'أسود'],
                    'en' => ['name' => 'Black'],
                ],
            ]))
            ->assertSessionHasErrors('code');
    }

    public function test_nested_value_must_belong_to_attribute_and_cross_attribute_ids_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $color = app(AttributeService::class)->create($this->attributePayload());
        $size = app(AttributeService::class)->create($this->attributePayload([
            'translations' => [
                'ar' => ['name' => 'مقاس'],
                'en' => ['name' => 'Size'],
            ],
        ]));

        $red = app(AttributeValueService::class)->create($color, $this->valuePayload());
        $small = app(AttributeValueService::class)->create($size, $this->valuePayload([
            'translations' => [
                'ar' => ['name' => 'صغير'],
                'en' => ['name' => 'Small'],
            ],
        ]));

        $this->actingAs($admin)
            ->get(route('admin.attribute-values.edit', [$color, $small]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->put(route('admin.attribute-values.update', [$color, $small]), $this->valuePayload([
                'code' => 'hijack',
            ]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->patch(route('admin.attribute-values.status', [$size, $red]), ['is_active' => 0])
            ->assertNotFound();

        $this->assertTrue($red->fresh()->is_active);
        $this->assertSame($color->id, $red->fresh()->attribute_id);
    }

    public function test_position_ordering_works_for_attributes_and_values(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(AttributeService::class);
        $values = app(AttributeValueService::class);

        $size = $service->create($this->attributePayload([
            'position' => 20,
            'translations' => [
                'ar' => ['name' => 'مقاس'],
                'en' => ['name' => 'Size'],
            ],
        ]));
        $color = $service->create($this->attributePayload([
            'position' => 10,
        ]));

        $this->assertSame(['color', 'size'], Attribute::query()->ordered()->pluck('code')->all());

        $values->create($color, $this->valuePayload([
            'position' => 5,
            'translations' => [
                'ar' => ['name' => 'أزرق'],
                'en' => ['name' => 'Blue'],
            ],
        ]));
        $values->create($color, $this->valuePayload([
            'position' => 1,
            'code' => 'red',
        ]));

        $this->assertSame(['red', 'blue'], $color->values()->ordered()->pluck('code')->all());

        $this->actingAs($admin)
            ->get(route('admin.attributes.index'))
            ->assertOk()
            ->assertSeeInOrder(['color', 'size']);
    }

    public function test_attribute_and_value_activation_does_not_delete_rows_or_translations(): void
    {
        $admin = User::factory()->admin()->create();
        $attribute = app(AttributeService::class)->create($this->attributePayload());
        $value = app(AttributeValueService::class)->create($attribute, $this->valuePayload());

        $this->actingAs($admin)
            ->patch(route('admin.attributes.status', $attribute), ['is_active' => 0])
            ->assertRedirect();
        $this->assertFalse($attribute->fresh()->is_active);
        $this->assertDatabaseCount('attribute_translations', 2);
        $this->assertDatabaseCount('attribute_values', 1);

        $this->actingAs($admin)
            ->patch(route('admin.attributes.status', $attribute), ['is_active' => 1])
            ->assertRedirect();
        $this->assertTrue($attribute->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.attribute-values.status', [$attribute, $value]), ['is_active' => 0])
            ->assertRedirect();
        $this->assertFalse($value->fresh()->is_active);
        $this->assertDatabaseCount('attribute_value_translations', 2);
        $this->assertDatabaseHas('attribute_values', ['id' => $value->id]);

        $this->actingAs($admin)
            ->patch(route('admin.attribute-values.status', [$attribute, $value]), ['is_active' => 1])
            ->assertRedirect();
        $this->assertTrue($value->fresh()->is_active);
    }

    public function test_admin_screens_render_and_no_hard_delete_routes_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create();

        $this->actingAs($admin)->get(route('admin.catalog'))->assertOk()->assertSee(__('Manage attributes'), false);
        $this->actingAs($admin)->get(route('admin.attributes.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.attributes.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.attributes.show', $attribute))->assertOk();
        $this->actingAs($admin)->get(route('admin.attributes.edit', $attribute))->assertOk();
        $this->actingAs($admin)->get(route('admin.attribute-values.create', $attribute))->assertOk();
        $this->actingAs($admin)->get(route('admin.attribute-values.edit', [$attribute, $value]))->assertOk();

        $this->assertFalse(Route::has('admin.attributes.destroy'));
        $this->assertFalse(Route::has('admin.attribute-values.destroy'));
        $this->assertNull(collect(Route::getRoutes())->first(
            fn ($route) => in_array('DELETE', $route->methods(), true)
                && (str_contains($route->uri(), 'admin/attributes') || str_contains($route->uri(), 'admin/attribute-values'))
        ));
    }

    public function test_simple_product_behavior_is_unchanged_and_no_destroy_or_conversion_routes_exist(): void
    {
        $vendor = $this->createVendorUser();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), [
                'type' => 'simple',
                'sku' => 'S4A-SIMPLE',
                'price' => '100',
                'quantity' => 2,
                'translations' => [
                    'en' => ['name' => 'Still Simple'],
                    'ar' => ['name' => ''],
                ],
            ])
            ->assertRedirect();

        $product = Product::query()->latest('id')->firstOrFail();
        $this->assertSame(ProductType::Simple, $product->type);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertNotNull($product->defaultVariant);

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), [
                'type' => 'variable',
                'sku' => 'S4A-VAR',
                'price' => '100',
                'quantity' => 1,
                'translations' => [
                    'en' => ['name' => 'Variable Attempt'],
                    'ar' => ['name' => ''],
                ],
            ])
            ->assertSessionHasErrors('attributes');

        $this->assertTrue(Schema::hasTable('product_attributes'));
        $this->assertTrue(Schema::hasTable('product_attribute_values'));
        $this->assertTrue(Schema::hasTable('product_variant_attribute_values'));
        $this->assertTrue(Schema::hasTable('attributes'));
        $this->assertTrue(Schema::hasTable('attribute_values'));
        $this->assertFalse(Schema::hasColumn('products', 'attribute_id'));
        $this->assertFalse(Route::has('vendor.products.matrix'));
        $this->assertFalse(Route::has('vendor.products.variants'));
    }
}
