<?php

namespace Tests\Feature;

use App\Enums\ProductReviewStatus;
use App\Enums\VendorOrderStatus;
use App\Models\OrderItem;
use App\Models\ParentOrder;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\VendorOrder;
use App\Services\ReviewService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewsRevCTest extends TestCase
{
    use RefreshDatabase;

    private ReviewService $reviews;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class]);
        $this->reviews = app(ReviewService::class);
    }

    public function test_staff_pending_queue_and_show(): void
    {
        $customer = User::factory()->create(['name' => 'Review Customer']);
        $other = User::factory()->create(['name' => 'Approved Author']);
        $staff = User::factory()->admin()->create(['preferred_locale' => 'en']);
        $product = Product::factory()->create();
        $this->seedDeliveredPurchase($customer, $product);
        $this->seedDeliveredPurchase($other, $product);

        $pending = $this->reviews->create($customer, $product, 4, 'Needs eyes');
        $approved = $this->reviews->create($other, $product, 5, 'Already public');
        $this->reviews->approve($staff, $approved);

        $this->actingAs($staff)
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertSee(__('Pending reviews'), false)
            ->assertSee('Review Customer', false)
            ->assertDontSee('Approved Author', false);

        $this->actingAs($staff)
            ->get(route('admin.reviews.show', $pending))
            ->assertOk()
            ->assertSee('Needs eyes', false)
            ->assertSee(__('Approve review'), false)
            ->assertSee(__('Reject review'), false)
            ->assertSee('Review Customer', false);
    }

    public function test_staff_approve_updates_visibility_and_aggregate(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->admin()->create(['preferred_locale' => 'en']);
        $product = Product::factory()->create();
        $this->seedDeliveredPurchase($customer, $product);

        $review = $this->reviews->create($customer, $product, 5, 'Ship it');

        $this->assertCount(0, $this->reviews->listApprovedForProduct($product));
        $this->assertSame(0, $this->reviews->approvedAggregateForProduct($product)['count']);

        $this->actingAs($staff)
            ->from(route('admin.reviews.show', $review))
            ->post(route('admin.reviews.approve', $review))
            ->assertRedirect(route('admin.reviews.show', $review))
            ->assertSessionHas('status', __('The review was approved.'));

        $review->refresh();
        $this->assertSame(ProductReviewStatus::Approved, $review->status);
        $this->assertCount(1, $this->reviews->listApprovedForProduct($product));

        $aggregate = $this->reviews->approvedAggregateForProduct($product->fresh());
        $this->assertSame(1, $aggregate['count']);
        $this->assertSame('5.00', $aggregate['average']);

        $this->actingAs($staff)
            ->get(route('admin.reviews.show', $review))
            ->assertOk()
            ->assertSee(__('The review was approved.'), false)
            ->assertDontSee(__('Approve review'), false);
    }

    public function test_staff_reject_hides_from_public_and_updates_aggregate(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $staff = User::factory()->admin()->create(['preferred_locale' => 'en']);
        $product = Product::factory()->create();
        $this->seedDeliveredPurchase($customer, $product);
        $this->seedDeliveredPurchase($other, $product);

        $keep = $this->reviews->create($customer, $product, 4, 'Keep me');
        $drop = $this->reviews->create($other, $product, 2, 'Drop me');
        $this->reviews->approve($staff, $keep);
        $this->reviews->approve($staff, $drop);

        $this->assertSame(2, $this->reviews->approvedAggregateForProduct($product->fresh())['count']);

        $this->actingAs($staff)
            ->from(route('admin.reviews.show', $drop))
            ->post(route('admin.reviews.reject', $drop))
            ->assertRedirect(route('admin.reviews.show', $drop))
            ->assertSessionHas('status', __('The review was rejected.'));

        $drop->refresh();
        $this->assertSame(ProductReviewStatus::Rejected, $drop->status);
        $this->assertCount(1, $this->reviews->listApprovedForProduct($product));

        $aggregate = $this->reviews->approvedAggregateForProduct($product->fresh());
        $this->assertSame(1, $aggregate['count']);
        $this->assertSame('4.00', $aggregate['average']);
    }

    public function test_non_staff_is_forbidden_on_moderation_routes(): void
    {
        $customer = User::factory()->create();
        $vendor = $this->createVendorUser();
        $product = Product::factory()->create();
        $this->seedDeliveredPurchase($customer, $product);
        $review = $this->reviews->create($customer, $product, 3, 'Private');

        foreach ([$customer, $vendor] as $actor) {
            $this->actingAs($actor)
                ->get(route('admin.reviews.index'))
                ->assertForbidden();

            $this->actingAs($actor)
                ->get(route('admin.reviews.show', $review))
                ->assertForbidden();

            $this->actingAs($actor)
                ->post(route('admin.reviews.approve', $review))
                ->assertForbidden();

            $this->actingAs($actor)
                ->post(route('admin.reviews.reject', $review))
                ->assertForbidden();
        }

        $this->assertSame(ProductReviewStatus::Pending, $review->fresh()->status);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $review = ProductReview::factory()->pending()->create();

        $this->get(route('admin.reviews.index'))
            ->assertRedirect(route('login'));

        $this->post(route('admin.reviews.approve', $review))
            ->assertRedirect(route('login'));

        $this->post(route('admin.reviews.reject', $review))
            ->assertRedirect(route('login'));
    }

    public function test_admin_review_ar_en_keys_have_parity(): void
    {
        $keys = [
            'Product reviews',
            'Pending reviews',
            'Moderate customer product reviews before they appear on the storefront.',
            'Review the content, then approve or reject it.',
            'Approve review',
            'Reject review',
            'The review was approved.',
            'The review was rejected.',
            'No reviews',
            'No product reviews match this filter.',
            'Review body',
            'Unable to update this review.',
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
            'sku' => 'REVC-SKU-'.uniqid(),
        ]);
    }
}
