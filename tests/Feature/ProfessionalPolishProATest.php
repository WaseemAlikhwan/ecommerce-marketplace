<?php

namespace Tests\Feature;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VendorOrder;
use App\Services\ProductImageService;
use App\Services\ProductPublicationService;
use App\Services\ProductService;
use App\Services\WishlistService;
use App\Support\Locale;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfessionalPolishProATest extends TestCase
{
    use RefreshDatabase;

    private WishlistService $wishlists;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            SyriaGeoSeeder::class,
        ]);

        $this->wishlists = app(WishlistService::class);
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_customer_with_no_orders_sees_live_empty_states_without_stale_copy(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);

        $response = $this->actingAs($customer)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(__('Your first order will live here'), false);
        $response->assertSee(__('Your wishlist is empty'), false);
        $response->assertSee(__('Save products while you browse the shop.'), false);
        $response->assertDontSee(__('When checkout launches, this becomes a timeline of parent orders.'), false);
        $response->assertDontSee(__('Heart a piece on the storefront to preview this list later.'), false);
        $response->assertDontSeeText('Nothing saved');
    }

    public function test_customer_sees_recent_orders_on_dashboard(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $vendor = $this->createVendorUser();

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        $vendorOrder = VendorOrder::factory()
            ->forStore($vendor->vendor->store)
            ->for($parent)
            ->create();

        Payment::factory()->for($vendorOrder)->create([
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
            'amount_minor' => $vendorOrder->grand_total_amount_minor,
            'currency_code' => $vendorOrder->currency_code,
        ]);

        $this->actingAs($customer)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText($parent->public_code)
            ->assertSee(route('account.orders.show', $parent), false)
            ->assertSee(__('Placed'), false)
            ->assertDontSee(__('When checkout launches, this becomes a timeline of parent orders.'), false)
            ->assertDontSeeText('No orders yet');
    }

    public function test_customer_sees_wishlist_count_on_dashboard(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $product = $this->publishVisibleProduct('DASH');
        $this->wishlists->add($customer, $product);

        $this->actingAs($customer)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('1 saved product'), false)
            ->assertDontSee(__('Your wishlist is empty'), false)
            ->assertDontSee(__('Heart a piece on the storefront to preview this list later.'), false);
    }

    public function test_vendor_without_customer_role_sees_dashboard_without_order_timeline(): void
    {
        $vendor = $this->createVendorUser();

        $this->actingAs($vendor)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Your first order will live here'), false)
            ->assertDontSee(__('When checkout launches, this becomes a timeline of parent orders.'), false);
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
            'sku' => 'PRO-'.$suffix.'-'.uniqid(),
            'price' => '150',
            'quantity' => 4,
            'translations' => [
                'ar' => ['name' => 'منتج '.$suffix],
                'en' => ['name' => 'Dashboard Product '.$suffix],
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
