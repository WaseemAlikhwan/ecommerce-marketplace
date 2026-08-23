<?php

namespace App\Support;

use Illuminate\Support\Str;

final class PublicOrderCode
{
    public static function parent(): string
    {
        return 'PO-'.strtoupper((string) Str::ulid());
    }

    public static function vendor(): string
    {
        return 'VO-'.strtoupper((string) Str::ulid());
    }
}
