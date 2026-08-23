<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CanonicalSlug
{
    public static function make(string $source, string $fallback): string
    {
        $base = Str::slug($source);
        $base = $base !== '' ? $base : Str::slug($fallback);
        $base = $base !== '' ? $base : $fallback;

        return Str::lower($base);
    }

    public static function unique(
        string $table,
        string $source,
        string $fallback,
        ?int $ignoreId = null,
        string $column = 'slug',
    ): string {
        $base = self::make($source, $fallback);
        $slug = $base;
        $i = 1;

        while (self::exists($table, $column, $slug, $ignoreId)) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private static function exists(string $table, string $column, string $slug, ?int $ignoreId): bool
    {
        $query = DB::table($table)->where($column, $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
