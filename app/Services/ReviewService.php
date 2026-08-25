<?php

namespace App\Services;

use App\Enums\ProductReviewStatus;
use App\Enums\VendorOrderStatus;
use App\Exceptions\ReviewException;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Authoritative product reviews (REV-A / OPEN-008 + OPEN-009 V1).
 *
 * Named ReviewService (not CheckoutReview*) to avoid checkout collisions.
 * No public SKU or exact inventory quantity is exposed from this service.
 */
class ReviewService
{
    public function customerIsEligible(User $actor, Product $product): bool
    {
        if (! $actor->isCustomer()) {
            return false;
        }

        return $this->hasDeliveredPurchase($actor, $product);
    }

    public function create(User $actor, Product $product, int $rating, ?string $body = null): ProductReview
    {
        $this->assertCustomer($actor);
        $this->assertEligible($actor, $product);
        $this->assertValidRating($rating);
        $normalizedBody = $this->normalizeBody($body);

        return DB::transaction(function () use ($actor, $product, $rating, $normalizedBody): ProductReview {
            $exists = ProductReview::query()
                ->where('user_id', $actor->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw ReviewException::conflict();
            }

            $review = ProductReview::query()->create([
                'user_id' => $actor->id,
                'product_id' => $product->id,
                'rating' => $rating,
                'body' => $normalizedBody,
                'status' => ProductReviewStatus::Pending,
            ]);

            $this->refreshProductAggregate($product);

            return $review;
        });
    }

    public function update(User $actor, ProductReview $review, int $rating, ?string $body = null): ProductReview
    {
        $this->assertCustomer($actor);
        $this->assertOwner($actor, $review);
        $this->assertEligible($actor, $review->product()->firstOrFail());
        $this->assertValidRating($rating);
        $normalizedBody = $this->normalizeBody($body);

        return DB::transaction(function () use ($review, $rating, $normalizedBody): ProductReview {
            /** @var ProductReview $locked */
            $locked = ProductReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'rating' => $rating,
                'body' => $normalizedBody,
                'status' => ProductReviewStatus::Pending,
            ])->save();

            $this->refreshProductAggregate($locked->product()->firstOrFail());

            return $locked->refresh();
        });
    }

    /**
     * @return Collection<int, ProductReview>
     */
    public function listApprovedForProduct(Product $product): Collection
    {
        return ProductReview::query()
            ->where('product_id', $product->id)
            ->where('status', ProductReviewStatus::Approved)
            ->latest('id')
            ->get();
    }

    /**
     * Owner-only fetch (including pending/rejected). Stranger → not found.
     */
    public function findOwned(User $actor, int $reviewId): ProductReview
    {
        $this->assertCustomer($actor);

        $review = ProductReview::query()
            ->whereKey($reviewId)
            ->where('user_id', $actor->id)
            ->first();

        if ($review === null) {
            throw ReviewException::notFound();
        }

        return $review;
    }

    public function approve(User $staff, ProductReview $review): ProductReview
    {
        $this->assertStaff($staff);

        return DB::transaction(function () use ($review): ProductReview {
            /** @var ProductReview $locked */
            $locked = ProductReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['status' => ProductReviewStatus::Approved])->save();
            $this->refreshProductAggregate($locked->product()->firstOrFail());

            return $locked->refresh();
        });
    }

    public function reject(User $staff, ProductReview $review): ProductReview
    {
        $this->assertStaff($staff);

        return DB::transaction(function () use ($review): ProductReview {
            /** @var ProductReview $locked */
            $locked = ProductReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['status' => ProductReviewStatus::Rejected])->save();
            $this->refreshProductAggregate($locked->product()->firstOrFail());

            return $locked->refresh();
        });
    }

    /**
     * Owner review for this product (any status), or null.
     */
    public function findForCustomerProduct(User $actor, Product $product): ?ProductReview
    {
        if (! $actor->isCustomer()) {
            return null;
        }

        return ProductReview::query()
            ->where('user_id', $actor->id)
            ->where('product_id', $product->id)
            ->first();
    }

    /**
     * Display-only approved aggregate. No SKU or inventory quantity.
     *
     * @return array{average: ?string, count: int}
     */
    public function approvedAggregateForProduct(Product $product): array
    {
        $row = Product::query()
            ->whereKey($product->id)
            ->first(['id', 'approved_rating_average', 'approved_reviews_count']);

        return [
            'average' => $row?->approved_rating_average !== null
                ? number_format((float) $row->approved_rating_average, 2, '.', '')
                : null,
            'count' => (int) ($row?->approved_reviews_count ?? 0),
        ];
    }

    private function hasDeliveredPurchase(User $actor, Product $product): bool
    {
        return OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('vendorOrder', function ($query) use ($actor): void {
                $query->where('status', VendorOrderStatus::Delivered)
                    ->whereHas('parentOrder', fn ($parent) => $parent->where('user_id', $actor->id));
            })
            ->exists();
    }

    private function refreshProductAggregate(Product $product): void
    {
        $row = ProductReview::query()
            ->where('product_id', $product->id)
            ->where('status', ProductReviewStatus::Approved)
            ->selectRaw('count(*) as aggregate_count, avg(rating) as aggregate_average')
            ->first();

        $count = (int) ($row?->aggregate_count ?? 0);
        $average = $count > 0 ? round((float) $row->aggregate_average, 2) : null;

        $product->forceFill([
            'approved_reviews_count' => $count,
            'approved_rating_average' => $average,
        ])->save();
    }

    private function assertCustomer(User $actor): void
    {
        if (! $actor->isCustomer()) {
            throw ReviewException::unauthorized();
        }
    }

    private function assertStaff(User $actor): void
    {
        if (! $actor->isStaff()) {
            throw ReviewException::unauthorized();
        }
    }

    private function assertOwner(User $actor, ProductReview $review): void
    {
        if (! $review->isOwnedBy($actor)) {
            throw ReviewException::notFound();
        }
    }

    private function assertEligible(User $actor, Product $product): void
    {
        if (! $this->hasDeliveredPurchase($actor, $product)) {
            throw ReviewException::ineligible();
        }
    }

    private function assertValidRating(int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw ReviewException::invalid();
        }
    }

    private function normalizeBody(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $trimmed = trim($body);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > 2000) {
            throw ReviewException::invalid();
        }

        return $trimmed;
    }
}
