<?php

namespace Tests\Feature;

use App\Enums\ProductReviewStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\ParentOrder;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\VendorOrder;
use App\Services\ProductImageService;
use App\Services\ProductPublicationService;
use App\Services\ProductService;
use App\Services\ReviewService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewsRevBTest extends TestCase
{
    use RefreshDatabase;

    private ReviewService $reviews;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class]);
        $this->reviews = app(ReviewService::class);
    }

    public function test_pdp_shows_approved_reviews_and_aggregate_without_sku_or_qty(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $staff = User::factory()->admin()->create();
        $product = $this->publishVisibleProduct('REV-PDP');
        $this->seedDeliveredPurchase($customer, $product);

        $review = $this->reviews->create($customer, $product, 5, 'Excellent item');
        $this->reviews->approve($staff, $review);

        $sku = $product->fresh(['defaultVariant'])->defaultVariant->sku;
        $qty = (int) $product->defaultVariant->quantity;

        $this->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertSee(__('Reviews'), false)
            ->assertSee('Excellent item', false)
            ->assertSee(__('Average rating :rating from :count reviews', [
                'rating' => '5.00',
                'count' => 1,
            ]), false)
            ->assertDontSee($sku, false)
            ->assertDontSee('quantity_available', false);

        $html = $this->get(route('storefront.product', $product->slug))->getContent();
        $this->assertDoesNotMatchRegularExpression(
            '/\b'.$qty.'\s*(available|in stock|قطعة|متاح)/iu',
            $html,
        );
    }

    public function test_eligible_customer_can_create_and_edit_review_via_http(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $product = $this->publishVisibleProduct('REV-HTTP');
        $this->seedDeliveredPurchase($customer, $product);

        $this->actingAs($customer)
            ->from(route('storefront.product', $product->slug))
            ->post(route('account.reviews.store', $product), [
                'rating' => 4,
                'body' => 'Solid purchase',
            ])
            ->assertRedirect(route('storefront.product', $product->slug))
            ->assertSessionHas('status', __('Review submitted for moderation.'));

        $review = ProductReview::query()->where('user_id', $customer->id)->first();
        $this->assertNotNull($review);
        $this->assertSame(ProductReviewStatus::Pending, $review->status);
        $this->assertSame(4, $review->rating);

        $this->actingAs($customer)
            ->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertSee(__('Update your review'), false)
            ->assertSee(__('Your review is pending moderation.'), false);

        $this->actingAs($customer)
            ->from(route('storefront.product', $product->slug))
            ->put(route('account.reviews.update', $review), [
                'rating' => 5,
                'body' => 'Even better after a week',
            ])
            ->assertRedirect(route('storefront.product', $product->slug))
            ->assertSessionHas('status', __('Review updated and awaiting moderation.'));

        $review->refresh();
        $this->assertSame(ProductReviewStatus::Pending, $review->status);
        $this->assertSame(5, $review->rating);
        $this->assertSame('Even better after a week', $review->body);
    }

    public function test_guest_mutate_redirects_to_login(): void
    {
        $product = $this->publishVisibleProduct('REV-GUEST');

        $this->post(route('account.reviews.store', $product), [
            'rating' => 5,
            'body' => 'Nope',
        ])->assertRedirect(route('login'));

        $review = ProductReview::factory()->create([
            'product_id' => $product->id,
            'status' => ProductReviewStatus::Pending,
        ]);

        $this->put(route('account.reviews.update', $review), [
            'rating' => 3,
            'body' => 'Nope',
        ])->assertRedirect(route('login'));
    }

    public function test_stranger_update_returns_404(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $product = $this->publishVisibleProduct('REV-ISO');
        $this->seedDeliveredPurchase($owner, $product);
        $this->seedDeliveredPurchase($stranger, $product);

        $review = $this->reviews->create($owner, $product, 3, 'Mine');

        $this->actingAs($stranger)
            ->put(route('account.reviews.update', $review), [
                'rating' => 1,
                'body' => 'Hijack',
            ])
            ->assertNotFound();

        $this->assertSame('Mine', $review->fresh()->body);
    }

    public function test_ineligible_customer_create_returns_404(): void
    {
        $customer = User::factory()->create();
        $product = $this->publishVisibleProduct('REV-INEL');

        $this->actingAs($customer)
            ->post(route('account.reviews.store', $product), [
                'rating' => 5,
                'body' => 'No purchase',
            ])
            ->assertNotFound();

        $this->assertSame(0, ProductReview::query()->count());
    }

    public function test_pdp_hides_pending_reviews_from_public_list(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $product = $this->publishVisibleProduct('REV-HIDE');
        $this->seedDeliveredPurchase($customer, $product);

        $this->reviews->create($customer, $product, 5, 'Secret pending text');

        $this->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertDontSee('Secret pending text', false)
            ->assertSee(__('No reviews yet.'), false);
    }

    public function test_review_ar_en_keys_have_parity(): void
    {
        $keys = [
            'Reviews',
            'No reviews yet.',
            'Average rating :rating from :count reviews',
            'Write a review',
            'Update your review',
            'Rating',
            'Your review',
            'Submit review',
            'Save review',
            'Choose a rating',
            'Review submitted for moderation.',
            'Review updated and awaiting moderation.',
            'You have already reviewed this product.',
            'Please choose a rating from 1 to 5.',
            'Your review is pending moderation.',
            'Your review was rejected. You may edit and resubmit.',
            'Editing will send your review back for moderation.',
            'You can review this product after a delivered purchase.',
            'Sign in to write a review.',
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
            'sku' => 'REVB-'.$suffix.'-'.uniqid(),
            'price' => '150',
            'quantity' => 4,
            'translations' => [
                'ar' => ['name' => 'منتج مراجعة '.$suffix],
                'en' => ['name' => 'Review Product '.$suffix],
            ],
        ]);

        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        auth()->logout();

        $product = $product->fresh(['defaultVariant']);
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        return $product;
    }

    private function seedDeliveredPurchase(
        User $customer,
        Product $product,
        VendorOrderStatus $status = VendorOrderStatus::Delivered,
    ): void {
        $vendorUser = $this->createVendorUser();
        $store = $vendorUser->vendor->store;

        $parent = ParentOrder::factory()->create([
            'user_id' => $customer->id,
        ]);

        $vendorOrder = VendorOrder::factory()
            ->forStore($store)
            ->for($parent)
            ->create([
                'status' => $status,
            ]);

        OrderItem::factory()->for($vendorOrder)->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'vendor_id' => $store->vendor_id,
            'quantity' => 1,
            'unit_price_amount_minor' => 1500,
            'line_total_amount_minor' => 1500,
            'currency_code' => $vendorOrder->currency_code,
            'sku' => 'REVB-SKU-'.uniqid(),
        ]);
    }
}
