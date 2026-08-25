<?php

namespace App\Coupons;

/**
 * Session key for the single applied checkout coupon code (CPN-B1).
 */
final class CheckoutCouponSession
{
    public const KEY = 'checkout.coupon_code';

    public static function get(): ?string
    {
        $code = session(self::KEY);

        return is_string($code) && $code !== '' ? strtoupper(trim($code)) : null;
    }

    public static function put(string $code): void
    {
        session([self::KEY => strtoupper(trim($code))]);
    }

    public static function forget(): void
    {
        session()->forget(self::KEY);
    }
}
