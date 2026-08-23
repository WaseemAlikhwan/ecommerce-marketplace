<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Requested locale → English → Arabic → stable canonical fallback.
 * Operates only on already-loaded translation collections (query-free).
 */
final class LocalizedText
{
    /**
     * @param  Collection<int, object>  $translations
     */
    public static function pick(
        Collection $translations,
        string $locale,
        string $attribute,
        ?string $canonicalFallback = null,
    ): ?string {
        $locale = Locale::sanitize($locale);
        $chain = array_values(array_unique([$locale, 'en', 'ar']));

        foreach ($chain as $candidate) {
            $row = $translations->firstWhere('locale', $candidate);
            if ($row === null) {
                continue;
            }
            $value = $row->{$attribute} ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        foreach ($translations as $row) {
            $value = $row->{$attribute} ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $canonicalFallback;
    }
}
