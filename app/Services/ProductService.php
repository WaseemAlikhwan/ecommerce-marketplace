<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Support\CanonicalSlug;
use App\Support\VariantEconomics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        private readonly ProductVariantMatrixService $matrix,
        private readonly ProductReadinessService $readiness,
    ) {}

    /**
     * @param  array{
     *     type?: string|null,
     *     slug?: string|null,
     *     category_id?: int|null,
     *     brand_id?: int|null,
     *     currency_code?: string|null,
     *     sku: string,
     *     price: string,
     *     compare_at_price?: string|null,
     *     quantity: int,
     *     translations: array<string, array{name?: string|null, short_description?: string|null, description?: string|null}>
     * }  $data
     */
    public function createSimpleDraft(Store $store, array $data): Product
    {
        $this->assertSimpleType($data['type'] ?? ProductType::Simple->value);
        $translations = $this->normalizeTranslations($data['translations'] ?? []);
        $this->assertAtLeastOneName($translations);

        $categoryId = $this->resolveCategoryId($data['category_id'] ?? null);
        $brandId = $this->resolveBrandId($data['brand_id'] ?? null);
        $currency = $this->resolveCurrency($store, $data['currency_code'] ?? null);
        $sku = VariantEconomics::normalizeSku($data['sku']);
        VariantEconomics::assertSkuAvailable($store->id, $sku);

        $priceMinor = VariantEconomics::parsePrice($data['price'], $currency->exponent);
        $compareAtMinor = VariantEconomics::parseOptionalCompareAt($data['compare_at_price'] ?? null, $currency->exponent, $priceMinor);
        $quantity = VariantEconomics::normalizeQuantity($data['quantity']);

        return DB::transaction(function () use (
            $store,
            $data,
            $translations,
            $categoryId,
            $brandId,
            $currency,
            $sku,
            $priceMinor,
            $compareAtMinor,
            $quantity,
        ): Product {
            $slug = $this->resolveSlug(
                $data['slug'] ?? null,
                $translations['en']['name'] ?? null,
                null,
            );

            $product = Product::query()->create([
                'store_id' => $store->id,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'slug' => $slug,
                'type' => ProductType::Simple,
                'status' => ProductStatus::Draft,
                'currency_code' => $currency->code,
            ]);

            $this->syncTranslations($product, $translations);

            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'sku' => $sku,
                'combination_key' => ProductVariant::DEFAULT_COMBINATION_KEY,
                'price_amount_minor' => $priceMinor,
                'compare_at_amount_minor' => $compareAtMinor,
                'quantity' => $quantity,
            ]);

            $product->forceFill(['default_variant_id' => $variant->id])->save();

            return $product->fresh(['translations', 'defaultVariant', 'currency', 'category', 'brand']);
        });
    }

    /**
     * @param  array{
     *     type?: string|null,
     *     slug?: string|null,
     *     category_id?: int|null,
     *     brand_id?: int|null,
     *     currency_code?: string|null,
     *     translations: array<string, array{name?: string|null, short_description?: string|null, description?: string|null}>,
     *     attributes: list<array{attribute_id: int|string, value_ids: list<int|string>}>,
     *     variants: list<array{
     *         value_ids: list<int|string>,
     *         sku: string,
     *         price: string,
     *         compare_at_price?: string|null,
     *         quantity: int|string,
     *         is_default?: bool|int|string|null
     *     }>
     * }  $data
     */
    public function createVariableDraft(Store $store, array $data): Product
    {
        $this->assertVariableType($data['type'] ?? ProductType::Variable->value);
        $translations = $this->normalizeTranslations($data['translations'] ?? []);
        $this->assertAtLeastOneName($translations);

        $matrix = [
            'attributes' => $data['attributes'] ?? [],
            'variants' => $data['variants'] ?? [],
        ];

        $this->matrix->assertWithinLimits($matrix);

        $categoryId = $this->resolveCategoryId($data['category_id'] ?? null);
        $brandId = $this->resolveBrandId($data['brand_id'] ?? null);
        $currency = $this->resolveCurrency($store, $data['currency_code'] ?? null);

        return DB::transaction(function () use (
            $store,
            $data,
            $translations,
            $categoryId,
            $brandId,
            $currency,
            $matrix,
        ): Product {
            $slug = $this->resolveSlug(
                $data['slug'] ?? null,
                $translations['en']['name'] ?? null,
                null,
            );

            $product = Product::query()->create([
                'store_id' => $store->id,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'slug' => $slug,
                'type' => ProductType::Variable,
                'status' => ProductStatus::Draft,
                'currency_code' => $currency->code,
            ]);

            $this->syncTranslations($product, $translations);

            return $this->matrix->sync($product, $matrix);
        });
    }

    /**
     * @param  array{
     *     attributes: list<array{attribute_id: int|string, value_ids: list<int|string>}>,
     *     variants: list<array{
     *         value_ids: list<int|string>,
     *         sku: string,
     *         price: string,
     *         compare_at_price?: string|null,
     *         quantity: int|string,
     *         is_default?: bool|int|string|null
     *     }>
     * }  $matrix
     */
    public function syncVariableMatrix(Product $product, array $matrix): Product
    {
        return DB::transaction(function () use ($product, $matrix): Product {
            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($product->type !== ProductType::Variable) {
                throw ValidationException::withMessages([
                    'type' => __('Product type cannot be changed after creation.'),
                ]);
            }

            return $this->matrix->sync($product, $matrix);
        });
    }

    /**
     * @param  array{
     *     type?: string|null,
     *     slug?: string|null,
     *     category_id?: int|null,
     *     brand_id?: int|null,
     *     currency_code?: string|null,
     *     translations: array<string, array{name?: string|null, short_description?: string|null, description?: string|null}>,
     *     attributes: list<array{attribute_id: int|string, value_ids: list<int|string>}>,
     *     variants: list<array{
     *         value_ids: list<int|string>,
     *         sku: string,
     *         price: string,
     *         compare_at_price?: string|null,
     *         quantity: int|string,
     *         is_default?: bool|int|string|null
     *     }>
     * }  $data
     */
    public function updateVariableDraft(Product $product, array $data): Product
    {
        $this->assertVariableType($data['type'] ?? ProductType::Variable->value);
        $translations = $this->normalizeTranslations($data['translations'] ?? []);
        $this->assertAtLeastOneName($translations);

        $matrix = [
            'attributes' => $data['attributes'] ?? [],
            'variants' => $data['variants'] ?? [],
        ];

        $this->matrix->assertWithinLimits($matrix);

        return DB::transaction(function () use ($product, $data, $translations, $matrix): Product {
            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($product->type !== ProductType::Variable) {
                throw ValidationException::withMessages([
                    'type' => __('Product type cannot be changed after creation.'),
                ]);
            }

            $requestedType = strtolower(trim((string) ($data['type'] ?? ProductType::Variable->value)));
            if ($requestedType !== $product->type->value) {
                throw ValidationException::withMessages([
                    'type' => __('Product type cannot be changed after creation.'),
                ]);
            }

            if (! $product->status->isVendorEditable()) {
                throw ValidationException::withMessages([
                    'status' => __('This product cannot be edited in its current status.'),
                ]);
            }

            $store = Store::query()->whereKey($product->store_id)->lockForUpdate()->firstOrFail();
            $categoryId = $this->resolveCategoryId($data['category_id'] ?? null, $product->category_id);
            $brandId = $this->resolveBrandId($data['brand_id'] ?? null, $product->brand_id);
            $currency = $this->resolveCurrency(
                $store,
                $data['currency_code'] ?? $product->currency_code,
                $product->currency_code,
            );

            $attributes = [
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'currency_code' => $currency->code,
            ];

            if (array_key_exists('slug', $data) && filled($data['slug'])) {
                $attributes['slug'] = CanonicalSlug::unique(
                    'products',
                    (string) $data['slug'],
                    'product',
                    $product->id,
                );
            }

            $product->fill($attributes)->save();
            $this->syncTranslations($product, $translations);
            $product->unsetRelation('currency');

            return $this->matrix->sync($product, $matrix);
        });
    }

    /**
     * @param  array{
     *     type?: string|null,
     *     slug?: string|null,
     *     category_id?: int|null,
     *     brand_id?: int|null,
     *     currency_code?: string|null,
     *     sku: string,
     *     price: string,
     *     compare_at_price?: string|null,
     *     quantity: int,
     *     translations: array<string, array{name?: string|null, short_description?: string|null, description?: string|null}>
     * }  $data
     */
    public function updateSimpleDraft(Product $product, array $data): Product
    {
        $this->assertSimpleType($data['type'] ?? ProductType::Simple->value);
        $translations = $this->normalizeTranslations($data['translations'] ?? []);
        $this->assertAtLeastOneName($translations);

        return DB::transaction(function () use ($product, $data, $translations): Product {
            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($product->type !== ProductType::Simple) {
                throw ValidationException::withMessages([
                    'type' => __('Only simple products can be managed in this release.'),
                ]);
            }

            $requestedType = strtolower(trim((string) ($data['type'] ?? ProductType::Simple->value)));
            if ($requestedType !== $product->type->value) {
                throw ValidationException::withMessages([
                    'type' => __('Product type cannot be changed after creation.'),
                ]);
            }

            if (! $product->status->isVendorEditable()) {
                throw ValidationException::withMessages([
                    'status' => __('This product cannot be edited in its current status.'),
                ]);
            }

            $store = Store::query()->whereKey($product->store_id)->lockForUpdate()->firstOrFail();
            $categoryId = $this->resolveCategoryId($data['category_id'] ?? null, $product->category_id);
            $brandId = $this->resolveBrandId($data['brand_id'] ?? null, $product->brand_id);
            $currency = $this->resolveCurrency(
                $store,
                $data['currency_code'] ?? $product->currency_code,
                $product->currency_code,
            );
            $sku = VariantEconomics::normalizeSku($data['sku']);
            $priceMinor = VariantEconomics::parsePrice($data['price'], $currency->exponent);
            $compareAtMinor = VariantEconomics::parseOptionalCompareAt(
                $data['compare_at_price'] ?? null,
                $currency->exponent,
                $priceMinor,
            );
            $quantity = VariantEconomics::normalizeQuantity($data['quantity']);

            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('id', $product->default_variant_id)
                ->lockForUpdate()
                ->firstOrFail();

            VariantEconomics::assertSkuAvailable($product->store_id, $sku, $variant->id);

            $attributes = [
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'currency_code' => $currency->code,
            ];

            if (array_key_exists('slug', $data) && filled($data['slug'])) {
                $attributes['slug'] = CanonicalSlug::unique(
                    'products',
                    (string) $data['slug'],
                    'product',
                    $product->id,
                );
            }

            $product->fill($attributes)->save();
            $this->syncTranslations($product, $translations);

            $variant->fill([
                'sku' => $sku,
                'store_id' => $product->store_id,
                'price_amount_minor' => $priceMinor,
                'compare_at_amount_minor' => $compareAtMinor,
                'quantity' => $quantity,
                'combination_key' => ProductVariant::DEFAULT_COMBINATION_KEY,
            ])->save();

            if ($product->default_variant_id !== $variant->id) {
                $product->forceFill(['default_variant_id' => $variant->id])->save();
            }

            $fresh = $product->fresh([
                'translations',
                'defaultVariant',
                'currency',
                'category',
                'brand',
                'images',
                'variants',
            ]);
            $this->readiness->assertIntegrityForPublished($fresh ?? $product);

            return $fresh ?? $product;
        });
    }

    public function archive(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            /** @var Product $product */
            $product = Product::withTrashed()->lockForUpdate()->findOrFail($product->id);

            if ($product->status === ProductStatus::Archived || $product->trashed()) {
                return $product;
            }

            if ($product->status === ProductStatus::Suspended) {
                throw ValidationException::withMessages([
                    'status' => __('Suspended products cannot be archived by the vendor.'),
                ]);
            }

            $product->status = ProductStatus::Archived;
            $product->save();
            $product->delete();

            ProductVariant::query()
                ->where('product_id', $product->id)
                ->get()
                ->each(function (ProductVariant $variant): void {
                    $variant->delete();
                });

            return Product::withTrashed()->findOrFail($product->id);
        });
    }

    /**
     * @param  array<string, array{name?: string|null, short_description?: string|null, description?: string|null}>  $translations
     * @return array<string, array{name: string, short_description: ?string, description: ?string}>
     */
    private function normalizeTranslations(array $translations): array
    {
        $normalized = [];

        foreach (['ar', 'en'] as $locale) {
            $payload = $translations[$locale] ?? [];
            $name = trim((string) ($payload['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $short = trim((string) ($payload['short_description'] ?? ''));
            $description = trim((string) ($payload['description'] ?? ''));

            $normalized[$locale] = [
                'name' => $name,
                'short_description' => $short !== '' ? $short : null,
                'description' => $description !== '' ? $description : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, array{name: string, short_description: ?string, description: ?string}>  $translations
     */
    private function assertAtLeastOneName(array $translations): void
    {
        if ($translations === []) {
            throw ValidationException::withMessages([
                'translations' => __('Provide at least an Arabic or English product name.'),
            ]);
        }
    }

    /**
     * @param  array<string, array{name: string, short_description: ?string, description: ?string}>  $translations
     */
    private function syncTranslations(Product $product, array $translations): void
    {
        foreach (['ar', 'en'] as $locale) {
            if (! isset($translations[$locale])) {
                $product->translations()->where('locale', $locale)->delete();

                continue;
            }

            $product->translations()->updateOrCreate(
                ['locale' => $locale],
                $translations[$locale],
            );
        }
    }

    private function resolveSlug(?string $explicit, ?string $englishName, ?int $ignoreId): string
    {
        if (filled($explicit)) {
            return CanonicalSlug::unique('products', (string) $explicit, 'product', $ignoreId);
        }

        if (filled($englishName)) {
            return CanonicalSlug::unique('products', (string) $englishName, 'product', $ignoreId);
        }

        $fallback = 'product-'.Str::lower((string) Str::ulid());

        return CanonicalSlug::unique('products', $fallback, $fallback, $ignoreId);
    }

    private function resolveCategoryId(mixed $categoryId, ?int $currentCategoryId = null): ?int
    {
        if ($categoryId === null || $categoryId === '') {
            return null;
        }

        $id = (int) $categoryId;
        $category = Category::query()->with('parent.parent')->find($id);

        if ($category === null) {
            throw ValidationException::withMessages([
                'category_id' => __('The selected category is invalid.'),
            ]);
        }

        if ($currentCategoryId !== null && $id === $currentCategoryId) {
            return $id;
        }

        if (! $category->isAssignableLeaf()) {
            throw ValidationException::withMessages([
                'category_id' => __('Select an active leaf category with active ancestors.'),
            ]);
        }

        return $category->id;
    }

    private function resolveBrandId(mixed $brandId, ?int $currentBrandId = null): ?int
    {
        if ($brandId === null || $brandId === '') {
            return null;
        }

        $id = (int) $brandId;
        $brand = Brand::query()->find($id);

        if ($brand === null) {
            throw ValidationException::withMessages([
                'brand_id' => __('Select an active brand.'),
            ]);
        }

        if ($currentBrandId !== null && $id === $currentBrandId) {
            return $id;
        }

        if (! $brand->is_active) {
            throw ValidationException::withMessages([
                'brand_id' => __('Select an active brand.'),
            ]);
        }

        return $brand->id;
    }

    private function resolveCurrency(Store $store, ?string $currencyCode, ?string $currentCurrencyCode = null): Currency
    {
        $code = strtoupper(trim((string) ($currencyCode ?: $store->default_currency_code)));

        $currency = Currency::query()->where('code', $code)->first();

        if ($currency === null) {
            throw ValidationException::withMessages([
                'currency_code' => __('Select an active supported currency.'),
            ]);
        }

        if ($currentCurrencyCode !== null && $code === strtoupper($currentCurrencyCode)) {
            return $currency;
        }

        if (! $currency->is_active) {
            throw ValidationException::withMessages([
                'currency_code' => __('Select an active supported currency.'),
            ]);
        }

        return $currency;
    }

    private function assertSimpleType(mixed $type): void
    {
        $value = is_string($type) ? strtolower(trim($type)) : ProductType::Simple->value;

        if ($value !== ProductType::Simple->value) {
            throw ValidationException::withMessages([
                'type' => __('Product type cannot be changed after creation.'),
            ]);
        }
    }

    private function assertVariableType(mixed $type): void
    {
        $value = is_string($type) ? strtolower(trim($type)) : ProductType::Variable->value;

        if ($value !== ProductType::Variable->value) {
            throw ValidationException::withMessages([
                'type' => __('Product type cannot be changed after creation.'),
            ]);
        }
    }
}
