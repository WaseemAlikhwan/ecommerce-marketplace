<?php

namespace App\Support;

use App\Exceptions\CouponException;

/**
 * Localized storefront messages for coupon error codes (CPN-B1).
 */
final class CouponMessage
{
    public static function forErrorCode(string $errorCode): string
    {
        return match ($errorCode) {
            CouponException::NOT_FOUND => __('This coupon code was not found.'),
            CouponException::INACTIVE => __('This coupon is no longer active.'),
            CouponException::EXPIRED => __('This coupon has expired or is not yet valid.'),
            CouponException::CURRENCY => __('This coupon does not apply to items in your cart currency.'),
            CouponException::MIN_NOT_MET => __('Your cart does not meet this coupon’s minimum.'),
            CouponException::LIMIT => __('This coupon has reached its usage limit.'),
            CouponException::CONFLICT => __('Only one coupon can be applied per checkout. Remove the current coupon first.'),
            CouponException::INVALID => __('This coupon cannot be applied.'),
            default => __('This coupon cannot be applied.'),
        };
    }
}
