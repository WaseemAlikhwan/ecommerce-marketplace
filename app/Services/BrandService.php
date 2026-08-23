<?php

namespace App\Services;

use App\Models\Brand;
use App\Support\CanonicalSlug;
use Illuminate\Support\Facades\DB;

class BrandService
{
    /**
     * @param  array{slug?: string|null, is_active?: bool, translations: array<string, array{name: string, description?: string|null}>}  $data
     */
    public function create(array $data): Brand
    {
        return DB::transaction(function () use ($data): Brand {
            $slug = $this->resolveSlug($data['slug'] ?? null, $data['translations']['en']['name'], null);

            $brand = Brand::query()->create([
                'slug' => $slug,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $this->syncTranslations($brand, $data['translations']);

            return $brand->load('translations');
        });
    }

    /**
     * @param  array{slug?: string|null, is_active?: bool, translations: array<string, array{name: string, description?: string|null}>}  $data
     */
    public function update(Brand $brand, array $data): Brand
    {
        return DB::transaction(function () use ($brand, $data): Brand {
            /** @var Brand $brand */
            $brand = Brand::query()->lockForUpdate()->findOrFail($brand->id);

            $attributes = [
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $brand->is_active,
            ];

            if (array_key_exists('slug', $data) && filled($data['slug'])) {
                $attributes['slug'] = CanonicalSlug::unique(
                    'brands',
                    (string) $data['slug'],
                    'brand',
                    $brand->id,
                );
            }

            $brand->fill($attributes)->save();
            $this->syncTranslations($brand, $data['translations']);

            return $brand->refresh()->load('translations');
        });
    }

    public function setActive(Brand $brand, bool $active): Brand
    {
        $brand->is_active = $active;
        $brand->save();

        return $brand->refresh();
    }

    /**
     * @param  array<string, array{name: string, description?: string|null}>  $translations
     */
    private function syncTranslations(Brand $brand, array $translations): void
    {
        foreach (['ar', 'en'] as $locale) {
            $payload = $translations[$locale] ?? null;

            if ($payload === null) {
                continue;
            }

            $brand->translations()->updateOrCreate(
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

        return CanonicalSlug::unique('brands', $source, 'brand', $ignoreId);
    }
}
