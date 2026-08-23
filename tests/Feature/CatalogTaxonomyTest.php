<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CatalogTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function categoryPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'parent_id' => null,
            'slug' => null,
            'position' => 0,
            'is_active' => 1,
            'translations' => [
                'ar' => ['name' => 'أزياء', 'description' => 'وصف عربي'],
                'en' => ['name' => 'Fashion', 'description' => 'English description'],
            ],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function brandPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'slug' => null,
            'is_active' => 1,
            'translations' => [
                'ar' => ['name' => 'نور', 'description' => null],
                'en' => ['name' => 'Nur Beauty', 'description' => null],
            ],
        ], $overrides);
    }

    public function test_guest_cannot_manage_taxonomy(): void
    {
        $this->get(route('admin.catalog'))->assertRedirect('/login');
        $this->get(route('admin.categories.index'))->assertRedirect('/login');
        $this->get(route('admin.brands.index'))->assertRedirect('/login');
        $this->post(route('admin.categories.store'), $this->categoryPayload())->assertRedirect('/login');
        $this->post(route('admin.brands.store'), $this->brandPayload())->assertRedirect('/login');
    }

    public function test_customer_cannot_manage_taxonomy(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('admin.catalog'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.categories.create'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.categories.store'), $this->categoryPayload())->assertForbidden();
        $this->actingAs($customer)->get(route('admin.brands.create'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.brands.store'), $this->brandPayload())->assertForbidden();
    }

    public function test_vendor_cannot_manage_taxonomy(): void
    {
        $vendor = $this->createVendorUser();

        $this->actingAs($vendor)->get(route('admin.catalog'))->assertForbidden();
        $this->actingAs($vendor)->post(route('admin.categories.store'), $this->categoryPayload())->assertForbidden();
        $this->actingAs($vendor)->post(route('admin.brands.store'), $this->brandPayload())->assertForbidden();
    }

    public function test_admin_can_create_category_with_arabic_and_english_translations(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), $this->categoryPayload())
            ->assertRedirect();

        $category = Category::query()->firstOrFail();
        $this->assertSame('fashion', $category->slug);
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->id,
            'locale' => 'ar',
            'name' => 'أزياء',
        ]);
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->id,
            'locale' => 'en',
            'name' => 'Fashion',
        ]);
    }

    public function test_super_admin_can_manage_brands(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->post(route('admin.brands.store'), $this->brandPayload())
            ->assertRedirect();

        $brand = Brand::query()->firstOrFail();
        $this->assertSame('nur-beauty', $brand->slug);
        $this->assertTrue($brand->is_active);
        $this->assertSame('Nur Beauty', $brand->name('en'));
        $this->assertSame('نور', $brand->name('ar'));
    }

    public function test_missing_required_translations_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), $this->categoryPayload([
                'translations' => [
                    'ar' => ['name' => ''],
                    'en' => ['name' => 'Fashion'],
                ],
            ]))
            ->assertSessionHasErrors('translations.ar.name');

        $this->actingAs($admin)
            ->post(route('admin.brands.store'), $this->brandPayload([
                'translations' => [
                    'ar' => ['name' => 'نور'],
                    'en' => ['name' => ''],
                ],
            ]))
            ->assertSessionHasErrors('translations.en.name');

        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('brands', 0);
    }

    public function test_canonical_slugs_are_unique_and_do_not_auto_change_when_names_change(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), $this->categoryPayload([
                'translations' => [
                    'ar' => ['name' => 'منزل'],
                    'en' => ['name' => 'Home'],
                ],
            ]))
            ->assertRedirect();

        $first = Category::query()->firstOrFail();
        $this->assertSame('home', $first->slug);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), $this->categoryPayload([
                'translations' => [
                    'ar' => ['name' => 'منزل ٢'],
                    'en' => ['name' => 'Home'],
                ],
            ]))
            ->assertRedirect();

        $second = Category::query()->where('id', '!=', $first->id)->firstOrFail();
        $this->assertSame('home-1', $second->slug);

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $first), $this->categoryPayload([
                'slug' => $first->slug,
                'translations' => [
                    'ar' => ['name' => 'بيت'],
                    'en' => ['name' => 'House'],
                ],
            ]))
            ->assertRedirect();

        $this->assertSame('home', $first->fresh()->slug);
        $this->assertSame('House', $first->fresh()->name('en'));
    }

    public function test_category_nesting_up_to_three_levels_succeeds_and_fourth_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(CategoryService::class);

        $root = $service->create($this->categoryPayload([
            'translations' => ['ar' => ['name' => 'جذر'], 'en' => ['name' => 'Root Cat']],
        ]));
        $mid = $service->create($this->categoryPayload([
            'parent_id' => $root->id,
            'translations' => ['ar' => ['name' => 'وسط'], 'en' => ['name' => 'Mid Cat']],
        ]));
        $leaf = $service->create($this->categoryPayload([
            'parent_id' => $mid->id,
            'translations' => ['ar' => ['name' => 'ورقة'], 'en' => ['name' => 'Leaf Cat']],
        ]));

        $this->assertSame(1, $root->fresh()->depth());
        $this->assertSame(2, $mid->fresh()->load('parent')->depth());
        $this->assertSame(3, $leaf->fresh()->load('parent.parent')->depth());

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), $this->categoryPayload([
                'parent_id' => $leaf->id,
                'translations' => ['ar' => ['name' => 'رابع'], 'en' => ['name' => 'Fourth']],
            ]))
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseCount('categories', 3);
    }

    public function test_self_parenting_and_cyclic_parent_changes_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(CategoryService::class);

        $root = $service->create($this->categoryPayload([
            'translations' => ['ar' => ['name' => 'أ'], 'en' => ['name' => 'Alpha']],
        ]));
        $child = $service->create($this->categoryPayload([
            'parent_id' => $root->id,
            'translations' => ['ar' => ['name' => 'ب'], 'en' => ['name' => 'Beta']],
        ]));

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $root), $this->categoryPayload([
                'parent_id' => $root->id,
                'slug' => $root->slug,
                'translations' => ['ar' => ['name' => 'أ'], 'en' => ['name' => 'Alpha']],
            ]))
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $root), $this->categoryPayload([
                'parent_id' => $child->id,
                'slug' => $root->slug,
                'translations' => ['ar' => ['name' => 'أ'], 'en' => ['name' => 'Alpha']],
            ]))
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_activation_and_deactivation_work_for_categories_and_brands(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.categories.status', $category), ['is_active' => 0])
            ->assertRedirect();
        $this->assertFalse($category->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.categories.status', $category), ['is_active' => 1])
            ->assertRedirect();
        $this->assertTrue($category->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.brands.status', $brand), ['is_active' => 0])
            ->assertRedirect();
        $this->assertFalse($brand->fresh()->is_active);
    }

    public function test_admin_catalog_screens_render_and_no_hard_delete_routes_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $this->actingAs($admin)->get(route('admin.catalog'))->assertOk()->assertSee(__('Manage categories'), false);
        $this->actingAs($admin)->get(route('admin.categories.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.categories.edit', $category))->assertOk();
        $this->actingAs($admin)->get(route('admin.brands.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.brands.edit', $brand))->assertOk();

        $this->assertFalse(Route::has('admin.categories.destroy'));
        $this->assertFalse(Route::has('admin.brands.destroy'));
        $this->assertNull(collect(Route::getRoutes())->first(
            fn ($route) => in_array('DELETE', $route->methods(), true)
                && (str_contains($route->uri(), 'admin/categories') || str_contains($route->uri(), 'admin/brands'))
        ));
    }

    public function test_explicit_slug_edit_enforces_uniqueness(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(CategoryService::class);

        $first = $service->create($this->categoryPayload([
            'translations' => ['ar' => ['name' => 'واحد'], 'en' => ['name' => 'One']],
        ]));
        $second = $service->create($this->categoryPayload([
            'translations' => ['ar' => ['name' => 'اثنان'], 'en' => ['name' => 'Two']],
        ]));

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $second), $this->categoryPayload([
                'slug' => $first->slug,
                'translations' => ['ar' => ['name' => 'اثنان'], 'en' => ['name' => 'Two']],
            ]))
            ->assertSessionHasErrors('slug');
    }
}
