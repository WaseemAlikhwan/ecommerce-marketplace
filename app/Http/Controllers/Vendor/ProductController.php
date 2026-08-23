<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreProductRequest;
use App\Http\Requests\Vendor\UpdateProductRequest;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Services\ProductReadinessService;
use App\Services\ProductService;
use App\Support\Money;
use App\Support\VendorProductFormState;
use App\Support\VendorProductGalleryState;
use App\Support\VendorProductReadinessState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $user = $request->user();
        $user->loadMissing(['roles', 'vendor.store']);
        $store = $user->vendor?->store;
        abort_unless($store, 404);

        $products = Product::withTrashed()
            ->forStore($store->id)
            ->with([
                'translations',
                'primaryImage.translations',
                'defaultVariant' => fn ($query) => $query->withTrashed(),
                'currency',
                'variants',
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('vendor.products.index', [
            'products' => $products,
            'store' => $store,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Product::class);

        $store = $request->user()->vendor?->store;
        abort_unless($store, 404);

        return view('vendor.products.create', $this->formViewData(null, $store->default_currency_code, true, false));
    }

    public function store(StoreProductRequest $request, ProductService $products): RedirectResponse
    {
        $store = $request->user()->vendor?->store;
        abort_unless($store, 404);

        $validated = $request->validated();
        $product = ($validated['type'] ?? ProductType::Simple->value) === ProductType::Variable->value
            ? $products->createVariableDraft($store, $this->variablePayload($validated))
            : $products->createSimpleDraft($store, $this->simplePayload($validated));

        return redirect()
            ->route('vendor.products.edit', $product)
            ->with('status', __('Product draft saved.'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('view', $product);

        $canEdit = auth()->user()?->can('update', $product) ?? false;
        $canArchive = auth()->user()?->can('archive', $product) ?? false;

        return view('vendor.products.edit', $this->formViewData(
            $product,
            $product->currency_code,
            $canEdit,
            $canArchive,
        ));
    }

    public function update(UpdateProductRequest $request, Product $product, ProductService $products): RedirectResponse
    {
        $validated = $request->validated();

        if ($product->type === ProductType::Variable) {
            $products->updateVariableDraft($product, $this->variablePayload($validated));
        } else {
            $products->updateSimpleDraft($product, $this->simplePayload($validated));
        }

        return redirect()
            ->route('vendor.products.edit', $product)
            ->with('status', __('Product draft updated.'));
    }

    public function archive(Product $product, ProductService $products): RedirectResponse
    {
        $this->authorize('archive', $product);

        $products->archive($product);

        return redirect()
            ->route('vendor.products')
            ->with('status', __('Product archived.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formViewData(?Product $product, string $defaultCurrencyCode, bool $canEdit, bool $canArchive): array
    {
        if ($product) {
            $product->load([
                'translations',
                'defaultVariant' => fn ($query) => $query->withTrashed(),
                'currency',
                'category',
                'brand',
                'productAttributesWithTrashed.attribute.translations',
                'productAttributesWithTrashed.selectedValuesWithTrashed.attributeValue.translations',
                'variantsWithTrashed.attributeValueLinks.productAttributeValue',
                'images.translations',
                'primaryImage.translations',
                'store.vendor',
            ]);
        }

        $variant = $product?->defaultVariant;
        $exponent = $product?->currency?->exponent ?? 0;
        $formInitiallyDirty = $product !== null && session()->hasOldInput();

        $readinessBootstrap = null;
        if ($product !== null) {
            $user = auth()->user();
            $result = app(ProductReadinessService::class)->evaluate($product);
            $readinessBootstrap = VendorProductReadinessState::from(
                $product,
                $result,
                $user?->can('publish', $product) ?? false,
                $user?->can('unpublish', $product) ?? false,
                $formInitiallyDirty,
            )->bootstrap();
        }

        return array_merge(
            $this->formData($defaultCurrencyCode, $product),
            [
                'product' => $product,
                'translations' => [
                    'ar' => $product?->translations->firstWhere('locale', 'ar'),
                    'en' => $product?->translations->firstWhere('locale', 'en'),
                ],
                'price' => old('price', $variant ? Money::formatFromMinor($variant->price_amount_minor, $exponent) : ''),
                'compare_at_price' => old('compare_at_price', $variant && $variant->compare_at_amount_minor !== null
                    ? Money::formatFromMinor($variant->compare_at_amount_minor, $exponent)
                    : ''),
                'sku' => old('sku', $variant?->sku ?? ''),
                'quantity' => old('quantity', $variant?->quantity ?? 0),
                'canEdit' => $canEdit,
                'canArchive' => $canArchive,
                'hasActiveAttributes' => Attribute::query()->active()->exists(),
                'matrixBootstrap' => VendorProductFormState::bootstrap($product, $defaultCurrencyCode, $canEdit),
                'galleryBootstrap' => $product ? VendorProductGalleryState::bootstrap($product, $canEdit) : null,
                'readinessBootstrap' => $readinessBootstrap,
            ],
        );
    }

    /**
     * @return array{
     *     categories: Collection<int, Category>,
     *     brands: \Illuminate\Database\Eloquent\Collection<int, Brand>,
     *     currencies: \Illuminate\Database\Eloquent\Collection<int, Currency>,
     *     defaultCurrencyCode: string
     * }
     */
    private function formData(string $defaultCurrencyCode, ?Product $product = null): array
    {
        $categories = Category::query()
            ->with(['translations', 'children', 'parent.parent'])
            ->whereNull('parent_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->flatMap(fn (Category $root) => $this->flattenAssignableLeaves($root));

        $brands = Brand::query()->active()->with('translations')->orderBy('id')->get();
        $currencies = Currency::query()->active()->orderBy('code')->get();

        if ($product !== null) {
            $categories = $this->includeCurrentCategoryOption($categories, $product);
            $brands = $this->includeCurrentBrandOption($brands, $product);
            $currencies = $this->includeCurrentCurrencyOption($currencies, $product);
        }

        return [
            'categories' => $categories,
            'brands' => $brands,
            'currencies' => $currencies,
            'defaultCurrencyCode' => $defaultCurrencyCode,
        ];
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>
     */
    private function includeCurrentCategoryOption(Collection $categories, Product $product): Collection
    {
        if ($product->category_id === null) {
            return $categories;
        }

        if ($categories->contains(fn (Category $category): bool => (int) $category->id === (int) $product->category_id)) {
            return $categories;
        }

        $current = Category::query()
            ->with(['translations', 'parent.translations', 'parent.parent.translations', 'children'])
            ->find($product->category_id);

        if ($current === null) {
            return $categories;
        }

        $current->setAttribute('option_label', $this->categoryPathLabel($current));
        $current->setAttribute('is_inactive_current', true);

        return $categories->prepend($current)->values();
    }

    private function categoryPathLabel(Category $category): string
    {
        $parts = [];
        $node = $category;
        while ($node !== null) {
            array_unshift($parts, $node->name());
            $node = $node->relationLoaded('parent') ? $node->parent : $node->parent()->with('translations')->first();
            if ($node !== null) {
                $node->loadMissing(['translations', 'parent.translations']);
            }
        }

        return implode(' / ', $parts);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Brand>  $brands
     * @return \Illuminate\Database\Eloquent\Collection<int, Brand>
     */
    private function includeCurrentBrandOption($brands, Product $product)
    {
        if ($product->brand_id === null) {
            return $brands;
        }

        if ($brands->contains(fn (Brand $brand): bool => (int) $brand->id === (int) $product->brand_id)) {
            return $brands;
        }

        $current = Brand::query()->with('translations')->find($product->brand_id);
        if ($current === null) {
            return $brands;
        }

        $current->setAttribute('is_inactive_current', true);

        return $brands->prepend($current)->values();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Currency>  $currencies
     * @return \Illuminate\Database\Eloquent\Collection<int, Currency>
     */
    private function includeCurrentCurrencyOption($currencies, Product $product)
    {
        $code = strtoupper((string) $product->currency_code);
        if ($currencies->contains(fn (Currency $currency): bool => $currency->code === $code)) {
            return $currencies;
        }

        $current = Currency::query()->where('code', $code)->first();
        if ($current === null) {
            return $currencies;
        }

        $current->setAttribute('is_inactive_current', true);

        return $currencies->prepend($current)->values();
    }

    /**
     * @return Collection<int, Category>
     */
    private function flattenAssignableLeaves(Category $category, string $prefix = '')
    {
        $label = ($prefix !== '' ? $prefix.' / ' : '').$category->name();
        $leaves = collect();

        $category->loadMissing(['children.translations', 'children.parent', 'translations']);

        if ($category->children->isEmpty()) {
            if ($category->isAssignableLeaf()) {
                $category->setAttribute('option_label', $label);
                $leaves->push($category);
            }

            return $leaves;
        }

        foreach ($category->children as $child) {
            $leaves = $leaves->merge($this->flattenAssignableLeaves($child, $label));
        }

        return $leaves;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function simplePayload(array $validated): array
    {
        return [
            'type' => $validated['type'] ?? ProductType::Simple->value,
            'slug' => $validated['slug'] ?? null,
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'brand_id' => isset($validated['brand_id']) ? (int) $validated['brand_id'] : null,
            'currency_code' => $validated['currency_code'] ?? null,
            'sku' => $validated['sku'],
            'price' => (string) $validated['price'],
            'compare_at_price' => isset($validated['compare_at_price']) ? (string) $validated['compare_at_price'] : null,
            'quantity' => (int) $validated['quantity'],
            'translations' => $this->translationPayload($validated),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function variablePayload(array $validated): array
    {
        $variants = [];
        foreach ($validated['variants'] ?? [] as $row) {
            $variants[] = [
                'value_ids' => array_map('intval', $row['value_ids'] ?? []),
                'sku' => (string) $row['sku'],
                'price' => (string) $row['price'],
                'compare_at_price' => filled($row['compare_at_price'] ?? null) ? (string) $row['compare_at_price'] : null,
                'quantity' => $row['quantity'],
                'is_default' => $row['is_default'] ?? false,
            ];
        }

        $attributes = [];
        foreach ($validated['attributes'] ?? [] as $row) {
            $attributes[] = [
                'attribute_id' => (int) $row['attribute_id'],
                'value_ids' => array_map('intval', $row['value_ids'] ?? []),
            ];
        }

        return [
            'type' => ProductType::Variable->value,
            'slug' => $validated['slug'] ?? null,
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'brand_id' => isset($validated['brand_id']) ? (int) $validated['brand_id'] : null,
            'currency_code' => $validated['currency_code'] ?? null,
            'translations' => $this->translationPayload($validated),
            'attributes' => $attributes,
            'variants' => $variants,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, array{name: ?string, short_description: ?string, description: ?string}>
     */
    private function translationPayload(array $validated): array
    {
        return [
            'ar' => [
                'name' => $validated['translations']['ar']['name'] ?? null,
                'short_description' => $validated['translations']['ar']['short_description'] ?? null,
                'description' => $validated['translations']['ar']['description'] ?? null,
            ],
            'en' => [
                'name' => $validated['translations']['en']['name'] ?? null,
                'short_description' => $validated['translations']['en']['short_description'] ?? null,
                'description' => $validated['translations']['en']['description'] ?? null,
            ],
        ];
    }
}
