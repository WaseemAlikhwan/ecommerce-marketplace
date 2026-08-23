<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\ReorderProductImagesRequest;
use App\Http\Requests\Vendor\StoreProductImageRequest;
use App\Http\Requests\Vendor\UpdateProductImageTranslationsRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use App\Services\ProductReadinessService;
use App\Support\VendorProductGalleryState;
use App\Support\VendorProductReadinessState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    public function store(StoreProductImageRequest $request, Product $product, ProductImageService $images): JsonResponse|RedirectResponse
    {
        $image = $images->upload($product, $request->file('image'));

        return $this->respond($request, $product, __('Product image uploaded.'), [
            'image_id' => $image->id,
            'primary_image_id' => $product->refresh()->primary_image_id,
        ], includeReadiness: true);
    }

    public function reorder(ReorderProductImagesRequest $request, Product $product, ProductImageService $images): JsonResponse|RedirectResponse
    {
        $images->reorder($product, $request->validated('image_ids'));

        return $this->respond($request, $product, __('Product images reordered.'));
    }

    public function primary(Request $request, Product $product, ProductImage $productImage, ProductImageService $images): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $product);
        $images->setPrimary($product, $productImage);

        return $this->respond($request, $product, __('Primary product image updated.'));
    }

    public function translations(
        UpdateProductImageTranslationsRequest $request,
        Product $product,
        ProductImage $productImage,
        ProductImageService $images,
    ): JsonResponse|RedirectResponse {
        $images->updateAltTexts($product, $productImage, $request->validated('translations') ?? []);

        return $this->respond($request, $product, __('Image text updated.'));
    }

    public function destroy(Request $request, Product $product, ProductImage $productImage, ProductImageService $images): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $product);
        $images->remove($product, $productImage);

        return $this->respond($request, $product, __('Product image removed.'), includeReadiness: true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function respond(
        Request $request,
        Product $product,
        string $status,
        array $payload = [],
        bool $includeReadiness = false,
    ): JsonResponse|RedirectResponse {
        $product = $product->fresh() ?? $product;
        $canEdit = $request->user()?->can('update', $product) ?? false;

        if ($request->expectsJson()) {
            $body = array_merge([
                'status' => $status,
                'primary_image_id' => $product->primary_image_id,
                'gallery' => VendorProductGalleryState::galleryPayload($product, $canEdit),
            ], $payload);

            if ($includeReadiness) {
                $user = $request->user();
                $result = app(ProductReadinessService::class)->evaluate($product);
                $body['readiness'] = VendorProductReadinessState::from(
                    $product,
                    $result,
                    $user?->can('publish', $product) ?? false,
                    $user?->can('unpublish', $product) ?? false,
                )->payload();
            }

            return response()->json($body);
        }

        return redirect()
            ->route('vendor.products.edit', $product)
            ->with('status', $status);
    }
}
