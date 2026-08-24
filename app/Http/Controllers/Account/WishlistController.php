<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\WishlistException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\DestroyWishlistItemRequest;
use App\Http\Requests\Account\StoreWishlistItemRequest;
use App\Models\Product;
use App\Models\WishlistItem;
use App\Services\Storefront\StorefrontProductQuery;
use App\Services\WishlistService;
use App\Storefront\Presentation\ProductCardPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class WishlistController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlists,
        private readonly StorefrontProductQuery $productQuery,
        private readonly ProductCardPresenter $cards,
    ) {}

    public function index(Request $request): View
    {
        if (! $request->user()?->can('viewAny', WishlistItem::class)) {
            abort(404);
        }

        $locale = app()->getLocale();
        $items = $this->wishlists->listFor($request->user());
        $products = $this->presentCards($items, $locale);

        return view('account.wishlist', [
            'products' => $products,
        ]);
    }

    public function store(StoreWishlistItemRequest $request, Product $product): RedirectResponse
    {
        try {
            $this->wishlists->add($request->user(), $product);
        } catch (WishlistException $e) {
            if ($e->errorCode === WishlistException::UNAUTHORIZED
                || $e->errorCode === WishlistException::NOT_FOUND) {
                abort(404);
            }

            throw $e;
        }

        return back(fallback: route('account.wishlist'))
            ->with('status', __('Added to wishlist.'));
    }

    public function destroy(
        DestroyWishlistItemRequest $request,
        WishlistItem $wishlistItem,
    ): RedirectResponse {
        $product = $wishlistItem->product;

        try {
            $this->wishlists->remove($request->user(), $product);
        } catch (WishlistException $e) {
            if ($e->errorCode === WishlistException::UNAUTHORIZED) {
                abort(404);
            }

            throw $e;
        }

        return back(fallback: route('account.wishlist'))
            ->with('status', __('Removed from wishlist.'));
    }

    /**
     * @param  Collection<int, WishlistItem>  $items
     * @return list<array{wishlist_item_id: int, card: array<string, mixed>}>
     */
    private function presentCards(Collection $items, string $locale): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $orderedIds = $items->pluck('product_id')->all();
        $itemIdsByProduct = $items->pluck('id', 'product_id');

        $products = Product::query()
            ->storefrontVisible()
            ->whereIn('id', $orderedIds)
            ->with($this->productQuery->cardRelations())
            ->get()
            ->keyBy('id');

        $this->productQuery->hydrateListingAggregates($products);

        $rows = [];
        foreach ($orderedIds as $productId) {
            $product = $products->get($productId);
            if ($product === null) {
                continue;
            }

            $rows[] = [
                'wishlist_item_id' => (int) $itemIdsByProduct->get($productId),
                'card' => $this->cards->present($product, $locale)->toArray(),
            ];
        }

        return $rows;
    }
}
