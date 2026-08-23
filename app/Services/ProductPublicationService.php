<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Support\ProductReadiness\ReadinessIssueMessages;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductPublicationService
{
    public function __construct(
        private readonly ProductReadinessService $readiness,
    ) {}

    public function publish(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($product->trashed()) {
                throw ValidationException::withMessages([
                    'status' => __('This product cannot be published in its current status.'),
                ]);
            }

            if ($product->status === ProductStatus::Published) {
                return $product->fresh([
                    'translations',
                    'defaultVariant',
                    'currency',
                    'category',
                    'brand',
                    'images',
                    'primaryImage',
                ]) ?? $product;
            }

            if (! in_array($product->status, [ProductStatus::Draft, ProductStatus::Unpublished], true)) {
                throw ValidationException::withMessages([
                    'status' => __('This product cannot be published in its current status.'),
                ]);
            }

            $result = $this->readiness->evaluate($product);

            if (! $result->isPublishable()) {
                throw ValidationException::withMessages(
                    ReadinessIssueMessages::toValidationMessages($result->publicationIssues())
                );
            }

            $attributes = [
                'status' => ProductStatus::Published,
            ];

            if ($product->published_at === null) {
                $attributes['published_at'] = now();
            }

            $product->forceFill($attributes)->save();

            return $product->fresh([
                'translations',
                'defaultVariant',
                'currency',
                'category',
                'brand',
                'images',
                'primaryImage',
            ]) ?? $product;
        });
    }

    public function unpublish(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($product->trashed()) {
                throw ValidationException::withMessages([
                    'status' => __('This product cannot be unpublished in its current status.'),
                ]);
            }

            if ($product->status === ProductStatus::Unpublished) {
                return $product->fresh() ?? $product;
            }

            if ($product->status !== ProductStatus::Published) {
                throw ValidationException::withMessages([
                    'status' => __('This product cannot be unpublished in its current status.'),
                ]);
            }

            $product->forceFill([
                'status' => ProductStatus::Unpublished,
            ])->save();

            return $product->fresh() ?? $product;
        });
    }
}
