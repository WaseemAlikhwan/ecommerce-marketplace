<?php

namespace App\Enums;

enum ProductType: string
{
    case Simple = 'simple';
    case Variable = 'variable';

    public function label(): string
    {
        return match ($this) {
            self::Simple => __('Simple'),
            self::Variable => __('Variable'),
        };
    }
}
