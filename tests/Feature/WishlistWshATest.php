<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Exceptions\WishlistException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\ProductImageService;
use App\Services\ProductPublicationService;
use App\Services\ProductService;
use App\Services\WishlistService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WishlistWshATest extends TestCase
{
    use RefreshDatabase;

    private WishlistService $wishlists;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class]);
        $this->wishlists = app(WishlistService::class);
    }

    public function test_customer_can_add_and_list_storefront_visible_product(): void
    {
        $customer = User::factory()->create();
        $product = $this->publishVisibleProduct('ADD');

        $item = $this->wishlists->add($customer, $product);

        $this->assertSame($customer->id, $item->user_id);
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame(1, WishlistItem::query()->count());

        $list = $this->wishlists->listFor($customer);
        $this->assertCount(1, $list);
        $this->assertTrue($list->first()->is($item));
        $this->assertTrue($customer->can('view', $item));
        $this->assertTrue($customer->can('create', WishlistItem::class));
    }

    public function test_re_add_is_idempotent(): void
    {
        $customer = User::factory()->create();
        $product = $this->publishVisibleProduct('IDEM');

        $first = $this->wishlists->add($customer, $product);
        $second = $this->wishlists->add($customer, $product);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, WishlistItem::query()->where('user_id', $customer->id)->count());
    }

    public function test_customer_can_remove_wishlist_item(): void
    {
        $customer = User::factory()->create();
        $product = $this->publishVisibleProduct('RM');

        $this->wishlists->add($customer, $product);
        $this->wishlists->remove($customer, $product);

        $this->assertSame(0, WishlistItem::query()->where('user_id', $customer->id)->count());
        $this->assertCount(0, $this->wishlists->listFor($customer));
    }

    public function test_uniqueness_is_enforced_per_customer_and_product(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $product = $this->publishVisibleProduct('UNIQ');

        $this->wishlists->add($customer, $product);
        $this->wishlists->add($other, $product);

        $this->assertSame(2, WishlistItem::query()->where('product_id', $product->id)->count());
        $this->assertSame(1, WishlistItem::query()->where('user_id', $customer->id)->count());

        $this->expectException(QueryException::class);
        WishlistItem::query()->create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_stranger_cannot_view_or_delete_foreign_item_and_remove_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $product = $this->publishVisibleProduct('ISO');

        $item = $this->wishlists->add($owner, $product);

        $this->assertFalse($stranger->can('view', $item));
        $this->assertFalse($stranger->can('delete', $item));
        $this->assertCount(0, $this->wishlists->listFor($stranger));

        $this->wishlists->remove($stranger, $product);

        $this->assertDatabaseHas('wishlist_items', [
            'id' => $item->id,
            'user_id' => $owner->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_non_visible_product_add_is_rejected(): void
    {
        $customer = User::factory()->create();
        $product = $this->publishVisibleProduct('HID');
        $product->forceFill(['status' => ProductStatus::Unpublished])->save();

        $this->assertFalse(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        try {
            $this->wishlists->add($customer, $product);
            $this->fail('Expected non-visible product add to fail');
        } catch (WishlistException $e) {
            $this->assertSame(WishlistException::NOT_FOUND, $e->errorCode);
        }

        $this->assertSame(0, WishlistItem::query()->count());
    }

    public function test_list_omits_products_that_are_no_longer_storefront_visible(): void
    {
        $customer = User::factory()->create();
        $product = $this->publishVisibleProduct('OMIT');
        $this->wishlists->add($customer, $product);

        $product->forceFill(['status' => ProductStatus::Draft])->save();

        $this->assertSame(1, WishlistItem::query()->where('user_id', $customer->id)->count());
        $this->assertCount(0, $this->wishlists->listFor($customer));
    }

    public function test_domain_surface_does_not_expose_sku_or_exact_quantity(): void
    {
        $customer = User::factory()->create();
        $product = $this->publishVisibleProduct('SAFE');
        $item = $this->wishlists->add($customer, $product);
        $listed = $this->wishlists->listFor($customer)->first();

        foreach ([$item, $listed] as $row) {
            $attrs = $row->getAttributes();
            $this->assertArrayHasKey('id', $attrs);
            $this->assertArrayHasKey('user_id', $attrs);
            $this->assertArrayHasKey('product_id', $attrs);
            $this->assertArrayNotHasKey('sku', $attrs);
            $this->assertArrayNotHasKey('quantity', $attrs);
            $this->assertSame([], array_diff(array_keys($attrs), [
                'id', 'user_id', 'product_id', 'created_at', 'updated_at',
            ]));
        }
    }

    public function test_non_customer_actor_is_rejected(): void
    {
        $vendor = $this->createVendorUser();
        $vendorRole = Role::query()->firstOrCreate(['name' => Role::VENDOR]);
        $vendor->roles()->sync([$vendorRole->id]);
        $vendor->unsetRelation('roles');
        $product = $this->publishVisibleProduct('VEN');

        $actor = $vendor->fresh(['roles']);
        $this->assertFalse($actor->isCustomer());

        try {
            $this->wishlists->add($actor, $product);
            $this->fail('Expected vendor without customer role to be rejected');
        } catch (WishlistException $e) {
            $this->assertSame(WishlistException::UNAUTHORIZED, $e->errorCode);
        }

        $this->assertFalse($actor->can('create', WishlistItem::class));
    }

    private function publishVisibleProduct(string $suffix): Product
    {
        Storage::fake('public');

        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);

        $category = Category::factory()->create(['is_active' => true]);
        $brand = Brand::factory()->create(['is_active' => true]);

        $product = app(ProductService::class)->createSimpleDraft($vendor->vendor->store, [
            'type' => 'simple',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'currency_code' => 'SYP',
            'sku' => 'WSH-'.$suffix.'-'.uniqid(),
            'price' => '150',
            'quantity' => 4,
            'translations' => [
                'ar' => ['name' => 'منتج قائمة '.$suffix],
                'en' => ['name' => 'Wishlist Product '.$suffix],
            ],
        ]);

        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        auth()->logout();

        $product = $product->fresh();
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        return $product;
    }
}
