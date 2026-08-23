<?php

namespace App\Enums;

enum StoreStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function isSellable(): bool
    {
        return $this === self::Active;
    }
}
