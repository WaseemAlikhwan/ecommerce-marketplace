<?php

namespace App\Services\Storefront;

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Storefront\CatalogAvailability;
use App\Storefront\CatalogCriteria;
use App\Storefront\CatalogCriteriaIssueCode;
use App\Storefront\CatalogCriteriaResult;
use App\Storefront\CatalogSort;
use App\Support\Locale;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Composable Storefront Product query foundation for public catalog routes.
 * Every browse/detail query starts from Product::storefrontVisible().
 */
final class StorefrontProductQuery
{
    public const RELATED_LIMIT = 4;

    public const AGG_MIN_PRICE = 'storefront_min_price_minor';

    public const AGG_MAX_PRICE = 'storefront_max_price_minor';

    public const AGG_IN_STOCK = 'storefront_in_stock';

    public const AGG_COMPARE_AT = 'storefront_compare_at_minor';

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, array{id: int, slug: string}>  $resolvedPath  trusted entities already resolved by a path controller
     */
    public function resolveCriteria(array $input, array $resolvedPath = []): CatalogCriteriaResult
    {
        $criteria = CatalogCriteria::fromInput($input);
        $issues = [];

        $categoryId = null;
        $categoryUnresolved = false;
        if ($criteria->categorySlug !== null) {
            $knownCategory = $resolvedPath['category'] ?? null;
            if (is_array($knownCategory)
                && ($knownCategory['slug'] ?? null) === $criteria->categorySlug
                && is_int($knownCategory['id'] ?? null)
                && $knownCategory['id'] > 0
            ) {
                $categoryId = $knownCategory['id'];
            } else {
                $category = Category::query()
                    ->storefrontNavigable()
                    ->where('slug', $criteria->categorySlug)
                    ->first();
                if ($category === null) {
                    $categoryUnresolved = true;
                    $issues[] = CatalogCriteriaIssueCode::CATEGORY_UNRESOLVED;
                } else {
                    $categoryId = (int) $category->id;
                }
            }
        }

        $brandId = null;
        $brandUnresolved = false;
        if ($criteria->brandSlug !== null) {
            $brand = Brand::query()->active()->where('slug', $criteria->brandSlug)->first();
            if ($brand === null) {
                $brandUnresolved = true;
                $issues[] = CatalogCriteriaIssueCode::BRAND_UNRESOLVED;
            } else {
                $brandId = (int) $brand->id;
            }
        }

        $storeId = null;
        $storeUnresolved = false;
        if ($criteria->storeSlug !== null) {
            $knownStore = $resolvedPath['store'] ?? null;
            if (is_array($knownStore)
                && ($knownStore['slug'] ?? null) === $criteria->storeSlug
                && is_int($knownStore['id'] ?? null)
                && $knownStore['id'] > 0
            ) {
                $storeId = $knownStore['id'];
            } else {
                $store = Store::query()->publiclyEligible()->where('slug', $criteria->storeSlug)->first();
                if ($store === null) {
                    $storeUnresolved = true;
                    $issues[] = CatalogCriteriaIssueCode::STORE_UNRESOLVED;
                } else {
                    $storeId = (int) $store->id;
                }
            }
        }

        $currencyCode = null;
        $currencyExponent = null;
        $currencyUnresolved = false;
        $minPriceMinor = null;
        $maxPriceMinor = null;

        if ($criteria->currencyCode !== null) {
            $currency = Currency::query()->active()->where('code', $criteria->currencyCode)->first();
            if ($currency === null) {
                $currencyUnresolved = true;
                $issues[] = CatalogCriteriaIssueCode::CURRENCY_UNRESOLVED;
            } else {
                $currencyCode = $currency->code;
                $currencyExponent = (int) $currency->exponent;

                if ($criteria->minPriceInput !== null) {
                    try {
                        $minPriceMinor = Money::parseToMinor($criteria->minPriceInput, $currencyExponent);
                    } catch (InvalidArgumentException) {
                        $issues[] = CatalogCriteriaIssueCode::PRICE_MIN_INVALID;
                    }
                }

                if ($criteria->maxPriceInput !== null) {
                    try {
                        $maxPriceMinor = Money::parseToMinor($criteria->maxPriceInput, $currencyExponent);
                    } catch (InvalidArgumentException) {
                        $issues[] = CatalogCriteriaIssueCode::PRICE_MAX_INVALID;
                    }
                }

                if ($minPriceMinor !== null && $maxPriceMinor !== null && $minPriceMinor > $maxPriceMinor) {
                    $issues[] = CatalogCriteriaIssueCode::PRICE_MIN_GT_MAX;
                    $minPriceMinor = null;
                    $maxPriceMinor = null;
                }
            }
        }

        $resolvedAttributeValueIds = [];
        $attributesUnresolved = false;
        $attributesByCode = $criteria->attributes === []
            ? collect()
            : Attribute::query()
                ->active()
                ->whereIn('code', array_keys($criteria->attributes))
                ->get(['id', 'code'])
                ->keyBy('code');

        $allValueCodes = array_values(array_unique(array_merge(...array_values($criteria->attributes ?: [[]]))));
        $valuesByAttributeAndCode = ($attributesByCode->isEmpty() || $allValueCodes === [])
            ? collect()
            : AttributeValue::query()
                ->active()
                ->whereIn('attribute_id', $attributesByCode->pluck('id'))
                ->whereIn('code', $allValueCodes)
                ->get(['id', 'attribute_id', 'code'])
                ->keyBy(static fn (AttributeValue $value): string => $value->attribute_id.':'.$value->code);

        foreach ($criteria->attributes as $attributeCode => $valueCodes) {
            /** @var Attribute|null $attribute */
            $attribute = $attributesByCode->get($attributeCode);
            if ($attribute === null) {
                $attributesUnresolved = true;
                $issues[] = CatalogCriteriaIssueCode::ATTRIBUTE_INACTIVE_OR_UNKNOWN;

                continue;
            }

            $ids = [];
            foreach ($valueCodes as $valueCode) {
                $value = $valuesByAttributeAndCode->get($attribute->id.':'.$valueCode);
                if ($value === null) {
                    $attributesUnresolved = true;
                    $issues[] = CatalogCriteriaIssueCode::ATTRIBUTE_VALUE_INACTIVE_OR_UNKNOWN;

                    continue;
                }
                $ids[] = (int) $value->id;
            }

            if ($ids !== []) {
                $resolvedAttributeValueIds[(string) $attribute->id] = array_values(array_unique($ids));
            }
        }

        return new CatalogCriteriaResult(
            criteria: $criteria,
            issues: array_values(array_unique([...$criteria->issues, ...$issues])),
            categoryId: $categoryId,
            brandId: $brandId,
            storeId: $storeId,
            currencyCode: $currencyCode,
            currencyExponent: $currencyExponent,
            minPriceMinor: $minPriceMinor,
            maxPriceMinor: $maxPriceMinor,
            resolvedAttributeValueIds: $resolvedAttributeValueIds,
            categoryUnresolved: $categoryUnresolved,
            brandUnresolved: $brandUnresolved,
            storeUnresolved: $storeUnresolved,
            currencyUnresolved: $currencyUnresolved,
            attributesUnresolved: $attributesUnresolved,
        );
    }

    /**
     * Browse builder with filters/sort/aggregates. Caller owns pagination.
     */
    public function browse(CatalogCriteriaResult $resolved, ?string $locale = null): Builder
    {
        $locale = Locale::sanitize($locale ?? app()->getLocale());
        $query = Product::query()->storefrontVisible();

        if ($resolved->hasUnresolvedFilters()) {
            return $query->whereRaw('0 = 1')->select($query->getModel()->getTable().'.*');
        }

        $this->applyListingAggregates($query);
        $this->applyFilters($query, $resolved);
        $this->applySort($query, $resolved, $locale);

        return $query;
    }

    /**
     * @return EloquentCollection<int, Product>
     */
    public function get(CatalogCriteriaResult $resolved, ?string $locale = null): EloquentCollection
    {
        return $this->browse($resolved, $locale)
            ->with($this->cardRelations())
            ->get();
    }

    public function findVisibleBySlugOrFail(string $slug): Product
    {
        $product = Product::query()
            ->storefrontVisible()
            ->where('slug', $slug)
            ->with($this->detailRelations())
            ->first();

        if ($product === null || ! $this->hasValidLiveDefaultVariant($product)) {
            throw (new ModelNotFoundException)->setModel(Product::class, [$slug]);
        }

        $related = $this->relatedProducts($product);
        $this->hydrateListingAggregates($related);
        $product->setRelation('relatedStorefrontProducts', $related);

        return $product;
    }

    /**
     * Public detail requires ≥1 live Variant and a live default among them.
     */
    private function hasValidLiveDefaultVariant(Product $product): bool
    {
        $liveVariants = $product->relationLoaded('variants')
            ? $product->variants
            : $product->variants()->get();

        if ($liveVariants->isEmpty()) {
            return false;
        }

        if ($liveVariants->count() > ProductVariant::MAX_LIVE_PER_PRODUCT) {
            return false;
        }

        $defaultId = $product->default_variant_id;
        if ($defaultId === null) {
            return false;
        }

        return $liveVariants->contains(
            fn (ProductVariant $variant): bool => (int) $variant->id === (int) $defaultId
        );
    }

    /**
     * Deterministic related Products: same leaf Category first, then same Store, max 4.
     *
     * @return EloquentCollection<int, Product>
     */
    public function relatedProducts(Product $product, int $limit = self::RELATED_LIMIT): EloquentCollection
    {
        $limit = max(0, min($limit, self::RELATED_LIMIT));
        if ($limit === 0) {
            return new EloquentCollection;
        }

        $query = Product::query()
            ->storefrontVisible()
            ->whereKeyNot([(int) $product->id])
            ->where(function (Builder $candidates) use ($product): void {
                if ($product->category_id !== null) {
                    $candidates->where('category_id', $product->category_id)
                        ->orWhere('store_id', $product->store_id);

                    return;
                }

                $candidates->where('store_id', $product->store_id);
            });

        if ($product->category_id !== null) {
            $query->orderByRaw(
                'case when products.category_id = ? then 0 else 1 end',
                [(int) $product->category_id],
            );
        }

        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->with($this->cardRelations())
            ->get()
            ->values();
    }

    /**
     * @return list<string|\Closure>
     */
    public function cardRelations(): array
    {
        return [
            'translations',
            'store',
            'currency',
            'primaryImage.translations',
        ];
    }

    /**
     * @return list<string|\Closure>
     */
    public function detailRelations(): array
    {
        return [
            'translations',
            'store',
            'currency',
            'brand.translations',
            'category.translations',
            'category.parent.translations',
            'category.parent.parent.translations',
            'images' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'images.translations',
            'primaryImage.translations',
            'variants' => fn ($q) => $q->orderBy('id'),
            'variants.attributeValueLinks.productAttributeValue.attributeValue.translations',
            'variants.attributeValueLinks.productAttributeValue.attributeValue.attribute.translations',
            'productAttributes' => fn ($q) => $q->ordered(),
            'productAttributes.attribute.translations',
            'productAttributes.selectedValues.attributeValue.translations',
        ];
    }

    private function applyListingAggregates(Builder $query): void
    {
        $productTable = $query->getModel()->getTable();
        $simple = ProductType::Simple->value;

        $minPrice = '(select min(pv.price_amount_minor) from product_variants pv'
            ." where pv.product_id = {$productTable}.id and pv.deleted_at is null)";
        $maxPrice = '(select max(pv.price_amount_minor) from product_variants pv'
            ." where pv.product_id = {$productTable}.id and pv.deleted_at is null)";
        $inStock = '(select case when exists(select 1 from product_variants pv'
            ." where pv.product_id = {$productTable}.id and pv.deleted_at is null and pv.quantity > 0)"
            .' then 1 else 0 end)';
        $compareAt = '(select case when '.$productTable.'.type = '.$this->quote($simple)
            .' then (select pv.compare_at_amount_minor from product_variants pv'
            ." where pv.product_id = {$productTable}.id and pv.deleted_at is null"
            .' order by pv.id asc limit 1) else null end)';

        $query->select($productTable.'.*')
            ->selectRaw($minPrice.' as '.self::AGG_MIN_PRICE)
            ->selectRaw($maxPrice.' as '.self::AGG_MAX_PRICE)
            ->selectRaw($inStock.' as '.self::AGG_IN_STOCK)
            ->selectRaw($compareAt.' as '.self::AGG_COMPARE_AT);
    }

    /**
     * @param  EloquentCollection<int, Product>  $products
     */
    public function hydrateListingAggregates(EloquentCollection $products): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $ids = $products->modelKeys();
        $rows = DB::table('product_variants')
            ->select('product_id')
            ->selectRaw('min(price_amount_minor) as '.self::AGG_MIN_PRICE)
            ->selectRaw('max(price_amount_minor) as '.self::AGG_MAX_PRICE)
            ->selectRaw('max(case when quantity > 0 then 1 else 0 end) as '.self::AGG_IN_STOCK)
            ->whereNull('deleted_at')
            ->whereIn('product_id', $ids)
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $simpleCompare = DB::table('product_variants')
            ->select(['product_id', 'compare_at_amount_minor', 'id'])
            ->whereNull('deleted_at')
            ->whereIn('product_id', $ids)
            ->orderBy('id')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($group) => $group->first()?->compare_at_amount_minor);

        foreach ($products as $product) {
            $agg = $rows->get($product->id);
            $product->setAttribute(self::AGG_MIN_PRICE, $agg?->{self::AGG_MIN_PRICE});
            $product->setAttribute(self::AGG_MAX_PRICE, $agg?->{self::AGG_MAX_PRICE});
            $product->setAttribute(self::AGG_IN_STOCK, (int) ($agg?->{self::AGG_IN_STOCK} ?? 0));
            $product->setAttribute(
                self::AGG_COMPARE_AT,
                $product->type === ProductType::Simple
                    ? $simpleCompare->get($product->id)
                    : null,
            );
        }
    }

    private function applyFilters(Builder $query, CatalogCriteriaResult $resolved): void
    {
        $criteria = $resolved->criteria;
        $productTable = $query->getModel()->getTable();

        if ($criteria->q !== null) {
            $like = $this->escapeLike($criteria->q);
            $query->whereExists(function ($sub) use ($like, $productTable): void {
                $sub->selectRaw('1')
                    ->from('product_translations')
                    ->whereColumn('product_translations.product_id', $productTable.'.id')
                    ->whereIn('product_translations.locale', ['ar', 'en'])
                    ->where(function ($text) use ($like): void {
                        $text->whereRaw('product_translations.name LIKE ? ESCAPE ?', [$like, '\\'])
                            ->orWhereRaw('product_translations.short_description LIKE ? ESCAPE ?', [$like, '\\']);
                    });
            });
        }

        if ($resolved->categoryId !== null) {
            $query->whereIn($productTable.'.category_id', $this->descendantLeafIds($resolved->categoryId));
        }

        if ($resolved->brandId !== null) {
            $query->where($productTable.'.brand_id', $resolved->brandId);
        }

        if ($resolved->storeId !== null) {
            $query->where($productTable.'.store_id', $resolved->storeId);
        }

        if ($resolved->currencyCode !== null) {
            $query->where($productTable.'.currency_code', $resolved->currencyCode);
        }

        if ($resolved->minPriceMinor !== null || $resolved->maxPriceMinor !== null) {
            $min = $resolved->minPriceMinor;
            $max = $resolved->maxPriceMinor;
            $query->whereExists(function ($sub) use ($productTable, $min, $max): void {
                $sub->selectRaw('1')
                    ->from('product_variants as price_filter_variants')
                    ->whereColumn('price_filter_variants.product_id', $productTable.'.id')
                    ->whereNull('price_filter_variants.deleted_at');
                if ($min !== null) {
                    $sub->where('price_filter_variants.price_amount_minor', '>=', $min);
                }
                if ($max !== null) {
                    $sub->where('price_filter_variants.price_amount_minor', '<=', $max);
                }
            });
        }

        if ($criteria->availability === CatalogAvailability::InStock) {
            $query->whereExists(function ($sub) use ($productTable): void {
                $sub->selectRaw('1')
                    ->from('product_variants as stock_variants')
                    ->whereColumn('stock_variants.product_id', $productTable.'.id')
                    ->whereNull('stock_variants.deleted_at')
                    ->where('stock_variants.quantity', '>', 0);
            });
        }

        if ($resolved->resolvedAttributeValueIds !== []) {
            $groups = array_values($resolved->resolvedAttributeValueIds);
            $query->whereExists(function ($sub) use ($productTable, $groups): void {
                $sub->selectRaw('1')
                    ->from('product_variants as attr_variants')
                    ->whereColumn('attr_variants.product_id', $productTable.'.id')
                    ->whereNull('attr_variants.deleted_at');

                foreach ($groups as $valueIds) {
                    $sub->whereExists(function ($attrSub) use ($valueIds): void {
                        $attrSub->selectRaw('1')
                            ->from('product_variant_attribute_values as pvav')
                            ->join('product_attribute_values as pav', 'pav.id', '=', 'pvav.product_attribute_value_id')
                            ->whereColumn('pvav.variant_id', 'attr_variants.id')
                            ->whereNull('pav.deleted_at')
                            ->whereIn('pav.attribute_value_id', $valueIds);
                    });
                }
            });
        }
    }

    private function applySort(Builder $query, CatalogCriteriaResult $resolved, string $locale): void
    {
        $productTable = $query->getModel()->getTable();

        match ($resolved->criteria->sort) {
            CatalogSort::PriceAsc => $query
                ->orderBy(self::AGG_MIN_PRICE)
                ->orderBy($productTable.'.id'),
            CatalogSort::PriceDesc => $query
                ->orderByDesc(self::AGG_MIN_PRICE)
                ->orderByDesc($productTable.'.id'),
            CatalogSort::Name => $this->orderByLocalizedName($query, $locale),
            CatalogSort::Newest => $query
                ->orderByDesc($productTable.'.published_at')
                ->orderByDesc($productTable.'.id'),
        };
    }

    private function orderByLocalizedName(Builder $query, string $locale): void
    {
        $productTable = $query->getModel()->getTable();
        $localeSql = $this->quote($locale);
        $nameExpr = '(select coalesce('
            .'(select pt.name from product_translations pt where pt.product_id = '.$productTable.'.id and pt.locale = '.$localeSql.' limit 1),'
            .'(select pt.name from product_translations pt where pt.product_id = '.$productTable.'.id and pt.locale = \'en\' limit 1),'
            .'(select pt.name from product_translations pt where pt.product_id = '.$productTable.'.id and pt.locale = \'ar\' limit 1),'
            .$productTable.'.slug'
            .'))';

        $query->orderByRaw($nameExpr.' asc')
            ->orderBy($productTable.'.id');
    }

    /**
     * @return list<int>
     */
    private function descendantLeafIds(int $categoryId): array
    {
        $category = Category::query()
            ->with([
                'children' => fn ($q) => $q->where('is_active', true)->with([
                    'children' => fn ($q2) => $q2->where('is_active', true),
                ]),
            ])
            ->find($categoryId);

        if ($category === null) {
            return [];
        }

        $leaves = [];
        $walk = function (Category $node) use (&$walk, &$leaves): void {
            $children = $node->relationLoaded('children') ? $node->children : collect();
            if ($children->isEmpty()) {
                $leaves[] = (int) $node->id;

                return;
            }
            foreach ($children as $child) {
                $walk($child);
            }
        };
        $walk($category);

        return array_values(array_unique($leaves));
    }

    private function escapeLike(string $term): string
    {
        $escaped = str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $term,
        );

        return '%'.$escaped.'%';
    }

    private function quote(string $value): string
    {
        return DB::getPdo()->quote($value);
    }
}
