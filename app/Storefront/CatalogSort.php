<?php

namespace App\Storefront;

enum CatalogSort: string
{
    case Newest = 'newest';
    case Name = 'name';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';

    public static function tryFromInput(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(strtolower(trim($value)));
    }
}
