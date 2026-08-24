<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WishlistWshBTest extends TestCase
{
    use RefreshDatabase;

    private WishlistService $wishlists;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class]);
        $this->wishlists = app(WishlistService::class);
    }

    public function test_owner_can_add_from_pdp_and_see_item_on_account_list(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $product = $this->publishVisibleProduct('UI-ADD');

        $this->actingAs($customer)
            ->from(route('storefront.product', $product->slug))
            ->post(route('account.wishlist.store', $product))
            ->assertRedirect(route('storefront.product', $product->slug))
            ->assertSessionHas('status', __('Added to wishlist.'));

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $response = $this->actingAs($customer)
            ->get(route('account.wishlist'));

        $response
            ->assertOk()
            ->assertSee('Wishlist Product UI-ADD', false)
            ->assertDontSee($product->defaultVariant->sku, false)
            ->assertDontSee('quantity_available', false);

        // Exact inventory must not appear as a labeled quantity on the wishlist surface.
        $this->assertDoesNotMatchRegularExpression(
            '/\b'.$product->defaultVariant->quantity.'\s*(available|in stock|قطعة|متاح)/iu',
            $response->getContent(),
        );
    }

    public function test_owner_can_remove_from_account_list(): void
    {
        $customer = User::factory()->create();
        $product = $this->publishVisibleProduct('UI-RM');
        $item = $this->wishlists->add($customer, $product);

        $this->actingAs($customer)
            ->from(route('account.wishlist'))
            ->delete(route('account.wishlist.destroy', $item))
            ->assertRedirect(route('account.wishlist'))
            ->assertSessionHas('status', __('Removed from wishlist.'));

        $this->assertDatabaseMissing('wishlist_items', ['id' => $item->id]);

        $this->actingAs($customer)
            ->get(route('account.wishlist'))
            ->assertOk()
            ->assertSee(__('Your wishlist is empty'), false);
    }

    public function test_pdp_shows_add_and_remove_controls_for_customer(): void
    {
        $customer = User::factory()->create();
        $product = $this->publishVisibleProduct('UI-PDP');

        $this->actingAs($customer)
            ->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertSee(__('Add to wishlist'), false)
            ->assertSee(route('account.wishlist.store', $product), false);

        $item = $this->wishlists->add($customer, $product);

        $this->actingAs($customer)
            ->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertSee(__('Remove from wishlist'), false)
            ->assertSee(route('account.wishlist.destroy', $item), false);
    }

    public function test_guest_mutate_redirects_to_login(): void
    {
        $product = $this->publishVisibleProduct('UI-GUEST');

        $this->post(route('account.wishlist.store', $product))
            ->assertRedirect(route('login'));

        $item = WishlistItem::query()->create([
            'user_id' => User::factory()->create()->id,
            'product_id' => $product->id,
        ]);

        $this->delete(route('account.wishlist.destroy', $item))
            ->assertRedirect(route('login'));

        $this->get(route('account.wishlist'))
            ->assertRedirect(route('login'));
    }

    public function test_stranger_cannot_delete_foreign_wishlist_item(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $product = $this->publishVisibleProduct('UI-ISO');
        $item = $this->wishlists->add($owner, $product);

        $this->actingAs($stranger)
            ->delete(route('account.wishlist.destroy', $item))
            ->assertNotFound();

        $this->assertDatabaseHas('wishlist_items', [
            'id' => $item->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_invisible_product_add_is_rejected_with_404(): void
    {
        $customer = User::factory()->create();
        $product = $this->publishVisibleProduct('UI-HID');
        $product->forceFill(['status' => ProductStatus::Unpublished])->save();

        $this->actingAs($customer)
            ->post(route('account.wishlist.store', $product))
            ->assertNotFound();

        $this->assertSame(0, WishlistItem::query()->count());
    }

    public function test_non_customer_cannot_use_wishlist_http(): void
    {
        $vendor = $this->createVendorUser();
        $vendorRole = Role::query()->firstOrCreate(['name' => Role::VENDOR]);
        $vendor->roles()->sync([$vendorRole->id]);
        $vendor = $vendor->fresh(['roles']);
        $this->assertFalse($vendor->isCustomer());

        $product = $this->publishVisibleProduct('UI-VEN');

        $this->actingAs($vendor)
            ->get(route('account.wishlist'))
            ->assertNotFound();

        $this->actingAs($vendor)
            ->post(route('account.wishlist.store', $product))
            ->assertNotFound();
    }

    public function test_wishlist_ar_en_keys_have_parity(): void
    {
        $keys = [
            'Saved products.',
            'Browse the catalog and save products you like.',
            'Added to wishlist.',
            'Removed from wishlist.',
            'Remove from wishlist',
            'Add to wishlist',
            'Your wishlist is empty',
            'Wishlist',
        ];

        $en = json_decode(file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
        $ar = json_decode(file_get_contents(lang_path('ar.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $en, "Missing EN key: {$key}");
            $this->assertArrayHasKey($key, $ar, "Missing AR key: {$key}");
            $this->assertNotSame('', trim((string) $en[$key]));
            $this->assertNotSame('', trim((string) $ar[$key]));
        }
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
            'sku' => 'WSHB-'.$suffix.'-'.uniqid(),
            'price' => '150',
            'quantity' => 4,
            'translations' => [
                'ar' => ['name' => 'منتج واجهة '.$suffix],
                'en' => ['name' => 'Wishlist Product '.$suffix],
            ],
        ]);

        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        auth()->logout();

        $product = $product->fresh(['defaultVariant']);
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        return $product;
    }
}
