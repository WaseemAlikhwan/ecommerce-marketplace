<?php

namespace App\Services;

use App\Models\Attribute;
use App\Support\CanonicalSlug;
use Illuminate\Support\Facades\DB;

class AttributeService
{
    /**
     * @param  array{code?: string|null, position?: int, is_active?: bool, translations: array<string, array{name: string}>}  $data
     */
    public function create(array $data): Attribute
    {
        return DB::transaction(function () use ($data): Attribute {
            $code = $this->resolveCode($data['code'] ?? null, $data['translations']['en']['name'], null);

            $attribute = Attribute::query()->create([
                'code' => $code,
                'position' => (int) ($data['position'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $this->syncTranslations($attribute, $data['translations']);

            return $attribute->load('translations');
        });
    }

    /**
     * @param  array{code?: string|null, position?: int, is_active?: bool, translations: array<string, array{name: string}>}  $data
     */
    public function update(Attribute $attribute, array $data): Attribute
    {
        return DB::transaction(function () use ($attribute, $data): Attribute {
            /** @var Attribute $attribute */
            $attribute = Attribute::query()->lockForUpdate()->findOrFail($attribute->id);

            $attributes = [
                'position' => array_key_exists('position', $data)
                    ? (int) $data['position']
                    : $attribute->position,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $attribute->is_active,
            ];

            if (array_key_exists('code', $data) && filled($data['code'])) {
                $attributes['code'] = CanonicalSlug::unique(
                    'attributes',
                    (string) $data['code'],
                    'attribute',
                    $attribute->id,
                    'code',
                );
            }

            $attribute->fill($attributes)->save();
            $this->syncTranslations($attribute, $data['translations']);

            return $attribute->refresh()->load('translations');
        });
    }

    public function setActive(Attribute $attribute, bool $active): Attribute
    {
        $attribute->is_active = $active;
        $attribute->save();

        return $attribute->refresh();
    }

    /**
     * @param  array<string, array{name: string}>  $translations
     */
    private function syncTranslations(Attribute $attribute, array $translations): void
    {
        foreach (['ar', 'en'] as $locale) {
            $payload = $translations[$locale] ?? null;

            if ($payload === null) {
                continue;
            }

            $attribute->translations()->updateOrCreate(
                ['locale' => $locale],
                ['name' => $payload['name']],
            );
        }
    }

    private function resolveCode(?string $explicit, string $englishName, ?int $ignoreId): string
    {
        $source = filled($explicit) ? (string) $explicit : $englishName;

        return CanonicalSlug::unique('attributes', $source, 'attribute', $ignoreId, 'code');
    }
}
