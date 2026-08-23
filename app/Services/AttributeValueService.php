<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Support\CanonicalSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttributeValueService
{
    /**
     * @param  array{code?: string|null, position?: int, is_active?: bool, translations: array<string, array{name: string}>}  $data
     */
    public function create(Attribute $attribute, array $data): AttributeValue
    {
        return DB::transaction(function () use ($attribute, $data): AttributeValue {
            $code = $this->resolveCode(
                $attribute->id,
                $data['code'] ?? null,
                $data['translations']['en']['name'],
                null,
            );

            $value = AttributeValue::query()->create([
                'attribute_id' => $attribute->id,
                'code' => $code,
                'position' => (int) ($data['position'] ?? 0),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $this->syncTranslations($value, $data['translations']);

            return $value->load('translations');
        });
    }

    /**
     * @param  array{code?: string|null, position?: int, is_active?: bool, translations: array<string, array{name: string}>}  $data
     */
    public function update(Attribute $attribute, AttributeValue $value, array $data): AttributeValue
    {
        $this->assertBelongsToAttribute($attribute, $value);

        return DB::transaction(function () use ($attribute, $value, $data): AttributeValue {
            /** @var AttributeValue $value */
            $value = AttributeValue::query()->lockForUpdate()->findOrFail($value->id);
            $this->assertBelongsToAttribute($attribute, $value);

            $attributes = [
                'position' => array_key_exists('position', $data)
                    ? (int) $data['position']
                    : $value->position,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : $value->is_active,
            ];

            if (array_key_exists('code', $data) && filled($data['code'])) {
                $attributes['code'] = $this->resolveCode(
                    $attribute->id,
                    (string) $data['code'],
                    $data['translations']['en']['name'],
                    $value->id,
                );
            }

            $value->fill($attributes)->save();
            $this->syncTranslations($value, $data['translations']);

            return $value->refresh()->load('translations');
        });
    }

    public function setActive(Attribute $attribute, AttributeValue $value, bool $active): AttributeValue
    {
        $this->assertBelongsToAttribute($attribute, $value);

        $value->is_active = $active;
        $value->save();

        return $value->refresh();
    }

    /**
     * @param  array<string, array{name: string}>  $translations
     */
    private function syncTranslations(AttributeValue $value, array $translations): void
    {
        foreach (['ar', 'en'] as $locale) {
            $payload = $translations[$locale] ?? null;

            if ($payload === null) {
                continue;
            }

            $value->translations()->updateOrCreate(
                ['locale' => $locale],
                ['name' => $payload['name']],
            );
        }
    }

    private function resolveCode(int $attributeId, ?string $explicit, string $englishName, ?int $ignoreId): string
    {
        $source = filled($explicit) ? (string) $explicit : $englishName;
        $base = CanonicalSlug::make($source, 'value');
        $code = $base;
        $i = 1;

        while ($this->codeExists($attributeId, $code, $ignoreId)) {
            $code = $base.'-'.$i;
            $i++;
        }

        return $code;
    }

    private function codeExists(int $attributeId, string $code, ?int $ignoreId): bool
    {
        $query = AttributeValue::query()
            ->where('attribute_id', $attributeId)
            ->where('code', $code);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function assertBelongsToAttribute(Attribute $attribute, AttributeValue $value): void
    {
        if ($value->attribute_id !== $attribute->id) {
            throw ValidationException::withMessages([
                'attribute_value' => __('The selected value does not belong to this attribute.'),
            ]);
        }
    }
}
