<?php

namespace Tests\Feature;

use App\Enums\ProductReviewStatus;
use App\Enums\VendorOrderStatus;
use App\Exceptions\ReviewException;
use App\Models\OrderItem;
use App\Models\ParentOrder;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Role;
use App\Models\User;
use App\Models\VendorOrder;
use App\Services\ReviewService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewsRevATest extends TestCase
{
    use RefreshDatabase;

    private ReviewService $reviews;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class]);
        $this->reviews = app(ReviewService::class);
    }

    public function test_eligible_customer_can_create_pending_review(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $this->seedDeliveredPurchase($customer, $product);

        $review = $this->reviews->create($customer, $product, 5, 'Great product');

        $this->assertSame($customer->id, $review->user_id);
        $this->assertSame($product->id, $review->product_id);
        $this->assertSame(5, $review->rating);
        $this->assertSame('Great product', $review->body);
        $this->assertSame(ProductReviewStatus::Pending, $review->status);
        $this->assertTrue($customer->can('create', ProductReview::class));
        $this->assertTrue($customer->can('view', $review));
        $this->assertTrue($customer->can('update', $review));
        $this->assertCount(0, $this->reviews->listApprovedForProduct($product));
    }

    public function test_ineligible_customer_cannot_create_review(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();

        try {
            $this->reviews->create($customer, $product, 4, null);
            $this->fail('Expected ineligible create to fail');
        } catch (ReviewException $e) {
            $this->assertSame(ReviewException::INELIGIBLE, $e->errorCode);
        }

        $this->seedDeliveredPurchase($customer, $product, VendorOrderStatus::Shipped);

        try {
            $this->reviews->create($customer, $product, 4, null);
            $this->fail('Expected non-delivered purchase to fail');
        } catch (ReviewException $e) {
            $this->assertSame(ReviewException::INELIGIBLE, $e->errorCode);
        }

        $this->assertSame(0, ProductReview::query()->count());
    }

    public function test_uniqueness_is_enforced_per_customer_and_product(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create();
        $this->seedDeliveredPurchase($customer, $product);
        $this->seedDeliveredPurchase($other, $product);

        $this->reviews->create($customer, $product, 3, null);
        $this->reviews->create($other, $product, 4, null);

        $this->assertSame(2, ProductReview::query()->where('product_id', $product->id)->count());

        try {
            $this->reviews->create($customer, $product, 5, 'again');
            $this->fail('Expected duplicate create to fail');
        } catch (ReviewException $e) {
            $this->assertSame(ReviewException::CONFLICT, $e->errorCode);
        }

        $this->expectException(QueryException::class);
        ProductReview::query()->create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'rating' => 1,
            'body' => null,
            'status' => ProductReviewStatus::Pending,
        ]);
    }

    public function test_update_returns_review_to_pending_and_stranger_is_isolated(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $staff = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $this->seedDeliveredPurchase($owner, $product);

        $review = $this->reviews->create($owner, $product, 2, 'ok');
        $approved = $this->reviews->approve($staff, $review);
        $this->assertSame(ProductReviewStatus::Approved, $approved->status);

        $updated = $this->reviews->update($owner, $approved, 5, 'edited');
        $this->assertSame(ProductReviewStatus::Pending, $updated->status);
        $this->assertSame(5, $updated->rating);
        $this->assertSame('edited', $updated->body);
        $this->assertCount(0, $this->reviews->listApprovedForProduct($product));

        $this->assertFalse($stranger->can('view', $updated));
        $this->assertFalse($stranger->can('update', $updated));

        try {
            $this->reviews->findOwned($stranger, $updated->id);
            $this->fail('Expected stranger findOwned to fail');
        } catch (ReviewException $e) {
            $this->assertSame(ReviewException::NOT_FOUND, $e->errorCode);
        }

        try {
            $this->reviews->update($stranger, $updated, 1, 'hijack');
            $this->fail('Expected stranger update to fail');
        } catch (ReviewException $e) {
            $this->assertSame(ReviewException::NOT_FOUND, $e->errorCode);
        }
    }

    public function test_moderation_transitions_and_thin_aggregate(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $staff = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $this->seedDeliveredPurchase($customer, $product);
        $this->seedDeliveredPurchase($other, $product);

        $first = $this->reviews->create($customer, $product, 4, null);
        $second = $this->reviews->create($other, $product, 2, null);

        $this->assertTrue($staff->can('moderate', $first));
        $this->assertFalse($customer->can('moderate', $first));

        $this->reviews->approve($staff, $first);
        $this->reviews->approve($staff, $second);

        $listed = $this->reviews->listApprovedForProduct($product);
        $this->assertCount(2, $listed);

        $aggregate = $this->reviews->approvedAggregateForProduct($product->fresh());
        $this->assertSame(2, $aggregate['count']);
        $this->assertSame('3.00', $aggregate['average']);
        $this->assertArrayNotHasKey('sku', $aggregate);
        $this->assertArrayNotHasKey('quantity', $aggregate);

        $this->reviews->reject($staff, $second->fresh());
        $aggregate = $this->reviews->approvedAggregateForProduct($product->fresh());
        $this->assertSame(1, $aggregate['count']);
        $this->assertSame('4.00', $aggregate['average']);
        $this->assertCount(1, $this->reviews->listApprovedForProduct($product));
    }

    public function test_domain_surface_does_not_expose_sku_or_exact_quantity(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $this->seedDeliveredPurchase($customer, $product);

        $review = $this->reviews->create($customer, $product, 5, 'safe');
        $this->reviews->approve($staff, $review);
        $listed = $this->reviews->listApprovedForProduct($product)->first();

        foreach ([$review->fresh(), $listed] as $row) {
            $attrs = $row->getAttributes();
            $this->assertArrayNotHasKey('sku', $attrs);
            $this->assertArrayNotHasKey('quantity', $attrs);
            $this->assertSame([], array_diff(array_keys($attrs), [
                'id', 'user_id', 'product_id', 'rating', 'body', 'status', 'created_at', 'updated_at',
            ]));
        }
    }

    public function test_invalid_rating_and_non_customer_are_rejected(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create();
        $this->seedDeliveredPurchase($customer, $product);

        try {
            $this->reviews->create($customer, $product, 0, null);
            $this->fail('Expected invalid rating to fail');
        } catch (ReviewException $e) {
            $this->assertSame(ReviewException::INVALID, $e->errorCode);
        }

        $vendor = $this->createVendorUser();
        $vendorRole = Role::query()->firstOrCreate(['name' => Role::VENDOR]);
        $vendor->roles()->sync([$vendorRole->id]);
        $vendor->unsetRelation('roles');
        $actor = $vendor->fresh(['roles']);
        $this->assertFalse($actor->isCustomer());

        try {
            $this->reviews->create($actor, $product, 5, null);
            $this->fail('Expected non-customer create to fail');
        } catch (ReviewException $e) {
            $this->assertSame(ReviewException::UNAUTHORIZED, $e->errorCode);
        }

        $this->assertFalse($actor->can('create', ProductReview::class));
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
            'sku' => 'REV-SKU-'.uniqid(),
        ]);
    }
}
