<?php

namespace App\Storefront;

enum CatalogAvailability: string
{
    case Any = 'any';
    case InStock = 'in_stock';

    public static function tryFromInput(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(strtolower(trim($value)));
    }
}
