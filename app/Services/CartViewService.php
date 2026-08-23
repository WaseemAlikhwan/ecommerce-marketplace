<?php

namespace App\Services;

use App\Cart\CartLine;
use App\Cart\CartView;
use App\Cart\CartViewPresenter;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Side-effect-free cart read path (C1-C).
 * Does not mutate session/DB cart rows when marking unavailable or stock-short lines.
 */
class CartViewService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CartViewPresenter $presenter,
    ) {}

    public function view(?User $user, ?string $locale = null): CartView
    {
        $cartLines = $this->carts->lines($user);

        if ($cartLines->isEmpty()) {
            return $this->presenter->present(collect(), collect(), [], $locale);
        }

        $variantIds = $cartLines->map(fn (CartLine $line): int => $line->variantId)->all();

        /** @var Collection<int, ProductVariant> $variants */
        $variants = ProductVariant::query()
            ->with([
                'product.translations',
                'product.currency',
                'product.store',
                'product.primaryImage.translations',
                'attributeValueLinks.productAttributeValue.attributeValue.translations',
                'attributeValueLinks.productAttributeValue.attributeValue.attribute.translations',
            ])
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $productIds = $variants
            ->map(fn (ProductVariant $variant): ?int => $variant->product_id !== null ? (int) $variant->product_id : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $visibleIds = [];
        if ($productIds !== []) {
            $visibleIds = Product::query()
                ->storefrontVisible()
                ->whereIn('id', $productIds)
                ->pluck('id')
                ->mapWithKeys(fn ($id): array => [(int) $id => true])
                ->all();
        }

        return $this->presenter->present($cartLines, $variants, $visibleIds, $locale);
    }
}
