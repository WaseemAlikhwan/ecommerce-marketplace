<?php

namespace App\Storefront\Presentation;

use App\Models\Category;
use App\Support\Locale;
use App\Support\LocalizedText;
use LogicException;

/**
 * Presents an eagerly loaded Category page graph without database access.
 */
final class CategoryPagePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Category $category, ?string $locale = null): array
    {
        $locale = Locale::sanitize($locale ?? app()->getLocale());
        $this->assertLoaded($category);

        $chain = [];
        $node = $category;
        while (true) {
            array_unshift($chain, $node);
            if ($node->parent_id === null) {
                break;
            }
            $node = $node->parent;
        }

        $breadcrumbs = array_map(fn (Category $item): array => [
            'label' => $this->name($item, $locale),
            'href' => route('storefront.category', $item->slug),
        ], $chain);

        return [
            'slug' => (string) $category->slug,
            'name' => $this->name($category, $locale),
            'description' => LocalizedText::pick($category->translations, $locale, 'description'),
            'url' => route('storefront.category', $category->slug),
            'breadcrumbs' => $breadcrumbs,
            'children' => $category->children
                ->map(fn (Category $child): array => [
                    'slug' => (string) $child->slug,
                    'name' => $this->name($child, $locale),
                    'description' => LocalizedText::pick($child->translations, $locale, 'description'),
                    'url' => route('storefront.category', $child->slug),
                ])
                ->values()
                ->all(),
        ];
    }

    private function assertLoaded(Category $category): void
    {
        if (! $category->relationLoaded('translations')) {
            throw new LogicException('CategoryPagePresenter requires loaded relation [translations].');
        }
        if (! $category->relationLoaded('children')) {
            throw new LogicException('CategoryPagePresenter requires loaded relation [children].');
        }

        foreach ($category->children as $child) {
            if (! $child->relationLoaded('translations')) {
                throw new LogicException('CategoryPagePresenter requires loaded relation [children.translations].');
            }
        }

        $node = $category;
        while ($node->parent_id !== null) {
            if (! $node->relationLoaded('parent') || $node->parent === null) {
                throw new LogicException('CategoryPagePresenter requires each declared ancestor to be loaded.');
            }
            $node = $node->parent;
            if (! $node->relationLoaded('translations')) {
                throw new LogicException('CategoryPagePresenter requires loaded ancestor translations.');
            }
        }
    }

    private function name(Category $category, string $locale): string
    {
        return LocalizedText::pick($category->translations, $locale, 'name', $category->slug) ?? $category->slug;
    }
}
