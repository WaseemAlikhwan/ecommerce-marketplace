<?php

namespace App\Services;

use App\Exceptions\CatalogTaxonomyException;
use App\Models\Category;
use App\Support\CanonicalSlug;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    /**
     * @param  array{parent_id?: int|null, slug?: string|null, position?: int, is_active?: bool, translations: array<string, array{name: string, description?: string|null}>}  $data
     */
    public function create(array $data): Category
    {
        return DB::transaction(function () use ($data): Category {
            $parentId = $data['parent_id'] ?? null;
            $this->assertValidParent(null, $parentId);

            $slug = $this->resolveSlug($data['slug'] ?? null, $data['translations']['en']['name'], null);

            $category = Category::query()->create([
                'parent_id' => $parentId,
                'slug' => $slug,
                'position' => (int) ($data['position'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $this->syncTranslations($category, $data['translations']);

            return $category->load('translations', 'parent');
        });
    }

    /**
     * @param  array{parent_id?: int|null, slug?: string|null, position?: int, is_active?: bool, translations: array<string, array{name: string, description?: string|null}>}  $data
     */
    public function update(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data): Category {
            /** @var Category $category */
            $category = Category::query()->lockForUpdate()->findOrFail($category->id);

            $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $category->parent_id;
            $this->assertValidParent($category, $parentId);

            $attributes = [
                'parent_id' => $parentId,
                'position' => (int) ($data['position'] ?? $category->position),
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $category->is_active,
            ];

            if (array_key_exists('slug', $data) && filled($data['slug'])) {
                $attributes['slug'] = CanonicalSlug::unique(
                    'categories',
                    (string) $data['slug'],
                    'category',
                    $category->id,
                );
            }

            $category->fill($attributes)->save();
            $this->syncTranslations($category, $data['translations']);

            return $category->refresh()->load('translations', 'parent');
        });
    }

    public function setActive(Category $category, bool $active): Category
    {
        $category->is_active = $active;
        $category->save();

        return $category->refresh();
    }

    /**
     * @param  array<string, array{name: string, description?: string|null}>  $translations
     */
    private function syncTranslations(Category $category, array $translations): void
    {
        foreach (['ar', 'en'] as $locale) {
            $payload = $translations[$locale] ?? null;

            if ($payload === null) {
                continue;
            }

            $category->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name' => $payload['name'],
                    'description' => $payload['description'] ?? null,
                ],
            );
        }
    }

    private function resolveSlug(?string $explicit, string $englishName, ?int $ignoreId): string
    {
        $source = filled($explicit) ? (string) $explicit : $englishName;

        return CanonicalSlug::unique('categories', $source, 'category', $ignoreId);
    }

    private function assertValidParent(?Category $category, mixed $parentId): void
    {
        if ($parentId === null || $parentId === '') {
            return;
        }

        $parentId = (int) $parentId;

        if ($category !== null && $parentId === $category->id) {
            throw CatalogTaxonomyException::selfParent();
        }

        $parent = Category::query()->with('parent')->find($parentId);

        if ($parent === null) {
            throw CatalogTaxonomyException::invalidParent();
        }

        if ($category !== null) {
            $descendantIds = $category->descendantIds();

            if ($descendantIds->contains($parentId)) {
                throw CatalogTaxonomyException::cyclicParent();
            }
        }

        $newDepth = $parent->depth() + 1;
        $heightBelow = $category?->subtreeHeightBelow() ?? 0;

        if ($newDepth + $heightBelow > Category::MAX_DEPTH) {
            throw CatalogTaxonomyException::maxDepthExceeded();
        }
    }
}
