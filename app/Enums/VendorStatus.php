<?php

namespace App\Enums;

enum VendorStatus: string
{
    case Approved = 'approved';
    case Suspended = 'suspended';

    public function canAccessPanel(): bool
    {
        return $this === self::Approved;
    }
}
