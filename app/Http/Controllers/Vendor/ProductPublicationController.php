<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductPublicationService;
use Illuminate\Http\RedirectResponse;

class ProductPublicationController extends Controller
{
    public function publish(Product $product, ProductPublicationService $publication): RedirectResponse
    {
        $this->authorize('publish', $product);

        $publication->publish($product);

        return redirect()
            ->route('vendor.products.edit', $product)
            ->with('status', __('Product published.'));
    }

    public function unpublish(Product $product, ProductPublicationService $publication): RedirectResponse
    {
        $this->authorize('unpublish', $product);

        $publication->unpublish($product);

        return redirect()
            ->route('vendor.products.edit', $product)
            ->with('status', __('Product unpublished.'));
    }
}
