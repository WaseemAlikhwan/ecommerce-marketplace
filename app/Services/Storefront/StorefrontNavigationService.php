<?php

namespace App\Services\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Support\Locale;
use App\Support\LocalizedText;

/**
 * Focused global-navigation read model: represented root Categories only.
 */
final class StorefrontNavigationService
{
    public const DESKTOP_CATEGORY_LIMIT = 5;

    /**
     * @return list<array{slug: string, name: string, url: string}>
     */
    public function categories(?string $locale = null): array
    {
        $locale = Locale::sanitize($locale ?? app()->getLocale());

        $representedRootIds = Product::query()
            ->storefrontVisible()
            ->join('categories as navigation_leaf', 'navigation_leaf.id', '=', 'products.category_id')
            ->leftJoin('categories as navigation_parent', 'navigation_parent.id', '=', 'navigation_leaf.parent_id')
            ->leftJoin('categories as navigation_root', 'navigation_root.id', '=', 'navigation_parent.parent_id')
            ->selectRaw('coalesce(navigation_root.id, navigation_parent.id, navigation_leaf.id)');

        return Category::query()
            ->storefrontNavigable()
            ->whereNull('parent_id')
            ->whereIn('categories.id', $representedRootIds)
            ->with('translations')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category): array => [
                'slug' => (string) $category->slug,
                'name' => LocalizedText::pick($category->translations, $locale, 'name', $category->slug) ?? $category->slug,
                'url' => route('storefront.category', $category->slug),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{slug: string, name: string, url: string}>
     */
    public function get(?string $locale = null): array
    {
        return $this->categories($locale);
    }
}
