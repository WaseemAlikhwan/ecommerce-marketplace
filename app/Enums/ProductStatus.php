<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
            self::Unpublished => __('Unpublished'),
            self::Suspended => __('Suspended'),
            self::Archived => __('Archived'),
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Published => 'success',
            self::Unpublished => 'warning',
            self::Suspended => 'danger',
            self::Archived => 'neutral',
        };
    }

    public function isVendorEditable(): bool
    {
        return match ($this) {
            self::Draft, self::Published, self::Unpublished => true,
            self::Suspended, self::Archived => false,
        };
    }
}
