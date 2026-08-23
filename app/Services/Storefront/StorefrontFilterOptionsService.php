<?php

namespace App\Services\Storefront;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Store;
use App\Support\Locale;
use App\Support\LocalizedText;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

/**
 * Builds the complete public filter dictionary as query-free arrays.
 */
final class StorefrontFilterOptionsService
{
    /**
     * @return array{
     *     categories: list<array<string, mixed>>,
     *     navigation: list<array<string, mixed>>,
     *     brands: list<array<string, mixed>>,
     *     stores: list<array<string, mixed>>,
     *     currencies: list<array<string, mixed>>,
     *     attributes: list<array<string, mixed>>
     * }
     */
    public function options(?string $locale = null): array
    {
        $locale = Locale::sanitize($locale ?? app()->getLocale());
        $categories = $this->categories($locale);

        return [
            'categories' => $categories,
            'navigation' => array_values(array_map(
                static fn (array $category): array => [
                    'slug' => $category['slug'],
                    'name' => $category['name'],
                    'url' => $category['url'],
                ],
                array_filter(
                    $categories,
                    static fn (array $category): bool => $category['parent_id'] === null,
                ),
            )),
            'brands' => $this->brands($locale),
            'stores' => $this->stores(),
            'currencies' => $this->currencies($locale),
            'attributes' => $this->attributes($locale),
        ];
    }

    /**
     * Alias suited to controller orchestration.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function get(?string $locale = null): array
    {
        return $this->options($locale);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categories(string $locale): array
    {
        $representedPaths = Product::query()
            ->storefrontVisible()
            ->join('categories as filter_leaf', 'filter_leaf.id', '=', 'products.category_id')
            ->leftJoin('categories as filter_parent', 'filter_parent.id', '=', 'filter_leaf.parent_id')
            ->leftJoin('categories as filter_root', 'filter_root.id', '=', 'filter_parent.parent_id')
            ->select([
                'filter_leaf.id as leaf_id',
                'filter_parent.id as parent_id',
                'filter_root.id as root_id',
            ])
            ->distinct()
            ->get();

        $representedIds = $representedPaths
            ->flatMap(static fn ($path): array => [
                $path->leaf_id,
                $path->parent_id,
                $path->root_id,
            ])
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($representedIds->isEmpty()) {
            return [];
        }

        $categories = Category::query()
            ->storefrontNavigable()
            ->whereIn('id', $representedIds)
            ->with('translations')
            ->get()
            ->keyBy('id');

        return $categories
            ->map(function (Category $category) use ($categories, $locale): array {
                $path = [];
                $sort = [];
                $node = $category;

                while ($node !== null) {
                    array_unshift(
                        $path,
                        LocalizedText::pick($node->translations, $locale, 'name', $node->slug) ?? $node->slug,
                    );
                    array_unshift($sort, sprintf('%010d-%020d', (int) $node->position, (int) $node->id));
                    $node = $node->parent_id === null
                        ? null
                        : $categories->get($node->parent_id);
                }

                return [
                    'slug' => (string) $category->slug,
                    'name' => end($path) ?: (string) $category->slug,
                    'label' => implode(' › ', $path),
                    'description' => LocalizedText::pick($category->translations, $locale, 'description'),
                    'parent_id' => $category->parent_id !== null ? (int) $category->parent_id : null,
                    'depth' => max(0, count($path) - 1),
                    'url' => route('storefront.category', $category->slug),
                    '_sort' => implode('/', $sort),
                ];
            })
            ->sortBy('_sort')
            ->map(static fn (array $category): array => [
                'slug' => $category['slug'],
                'name' => $category['name'],
                'label' => $category['label'],
                'description' => $category['description'],
                'parent_id' => $category['parent_id'],
                'depth' => $category['depth'],
                'url' => $category['url'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function brands(string $locale): array
    {
        $visibleBrandIds = Product::query()
            ->storefrontVisible()
            ->whereNotNull('brand_id')
            ->select('products.brand_id');

        return Brand::query()
            ->active()
            ->whereIn('brands.id', $visibleBrandIds)
            ->with('translations')
            ->orderBy('slug')
            ->get()
            ->map(fn (Brand $brand): array => [
                'slug' => (string) $brand->slug,
                'name' => LocalizedText::pick($brand->translations, $locale, 'name', $brand->slug) ?? $brand->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stores(): array
    {
        return Store::query()
            ->publiclyEligible()
            ->whereHas('products', fn ($query) => $query->storefrontVisible())
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Store $store): array => [
                'slug' => (string) $store->slug,
                'name' => (string) $store->name,
                'url' => route('storefront.store', $store->slug),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function currencies(string $locale): array
    {
        $visibleCurrencyCodes = Product::query()
            ->storefrontVisible()
            ->select('currency_code')
            ->distinct();

        return Currency::query()
            ->active()
            ->whereIn('code', $visibleCurrencyCodes)
            ->orderBy('code')
            ->get()
            ->map(fn (Currency $currency): array => [
                'code' => (string) $currency->code,
                'exponent' => (int) $currency->exponent,
                'label' => Lang::get(
                    $currency->code === 'SYP' ? 'Syrian Pound (SYP)' : ($currency->code === 'USD' ? 'US Dollar (USD)' : $currency->code),
                    [],
                    $locale,
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attributes(string $locale): array
    {
        $visibleProductIds = Product::query()
            ->storefrontVisible()
            ->select('products.id');

        $rows = DB::table('product_variant_attribute_values as pvav')
            ->join('product_variants as pv', function ($join): void {
                $join->on('pv.id', '=', 'pvav.variant_id')
                    ->on('pv.product_id', '=', 'pvav.product_id');
            })
            ->join('product_attribute_values as pav', 'pav.id', '=', 'pvav.product_attribute_value_id')
            ->join('product_attributes as pa', 'pa.id', '=', 'pav.product_attribute_id')
            ->join('attribute_values as av', 'av.id', '=', 'pav.attribute_value_id')
            ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->leftJoin('attribute_translations as attribute_requested', function (JoinClause $join) use ($locale): void {
                $join->on('attribute_requested.attribute_id', '=', 'a.id')
                    ->where('attribute_requested.locale', $locale);
            })
            ->leftJoin('attribute_translations as attribute_en', function (JoinClause $join): void {
                $join->on('attribute_en.attribute_id', '=', 'a.id')
                    ->where('attribute_en.locale', 'en');
            })
            ->leftJoin('attribute_translations as attribute_ar', function (JoinClause $join): void {
                $join->on('attribute_ar.attribute_id', '=', 'a.id')
                    ->where('attribute_ar.locale', 'ar');
            })
            ->leftJoin('attribute_value_translations as value_requested', function (JoinClause $join) use ($locale): void {
                $join->on('value_requested.attribute_value_id', '=', 'av.id')
                    ->where('value_requested.locale', $locale);
            })
            ->leftJoin('attribute_value_translations as value_en', function (JoinClause $join): void {
                $join->on('value_en.attribute_value_id', '=', 'av.id')
                    ->where('value_en.locale', 'en');
            })
            ->leftJoin('attribute_value_translations as value_ar', function (JoinClause $join): void {
                $join->on('value_ar.attribute_value_id', '=', 'av.id')
                    ->where('value_ar.locale', 'ar');
            })
            ->whereNull('pv.deleted_at')
            ->whereNull('pav.deleted_at')
            ->whereNull('pa.deleted_at')
            ->where('a.is_active', true)
            ->where('av.is_active', true)
            ->whereIn('pvav.product_id', $visibleProductIds)
            ->select([
                'a.id as attribute_id',
                'a.code as attribute_code',
                'a.position as attribute_position',
                'av.id as value_id',
                'av.code as value_code',
                'av.position as value_position',
            ])
            ->selectRaw('coalesce(attribute_requested.name, attribute_en.name, attribute_ar.name, a.code) as attribute_name')
            ->selectRaw('coalesce(value_requested.name, value_en.name, value_ar.name, av.code) as value_name')
            ->distinct()
            ->orderBy('a.position')
            ->orderBy('a.id')
            ->orderBy('av.position')
            ->orderBy('av.id')
            ->get();

        $attributes = [];
        foreach ($rows as $row) {
            $attributeId = (int) $row->attribute_id;
            if (! array_key_exists($attributeId, $attributes)) {
                $attributes[$attributeId] = [
                    'code' => (string) $row->attribute_code,
                    'name' => (string) $row->attribute_name,
                    'values' => [],
                ];
            }

            $attributes[$attributeId]['values'][] = [
                'code' => (string) $row->value_code,
                'name' => (string) $row->value_name,
            ];
        }

        return array_values($attributes);
    }
}
