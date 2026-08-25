<?php

namespace App\Exceptions;

use RuntimeException;

class CheckoutException extends RuntimeException
{
    public const EMPTY_CART = 'empty_cart';

    public const UNAVAILABLE_VARIANT = 'unavailable_variant';

    public const INSUFFICIENT_STOCK = 'insufficient_stock';

    public const INVALID_ADDRESS = 'invalid_address';

    public const MIXED_CURRENCY_VENDOR = 'mixed_currency_vendor';

    public const COMMISSION_UNCONFIGURED = 'commission_unconfigured';

    public const COUPON_REJECTED = 'coupon_rejected';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        public readonly ?string $couponErrorCode = null,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public static function emptyCart(): self
    {
        return new self(self::EMPTY_CART);
    }

    public static function unavailableVariant(): self
    {
        return new self(self::UNAVAILABLE_VARIANT);
    }

    public static function insufficientStock(): self
    {
        return new self(self::INSUFFICIENT_STOCK);
    }

    public static function invalidAddress(): self
    {
        return new self(self::INVALID_ADDRESS);
    }

    public static function mixedCurrencyVendor(): self
    {
        return new self(self::MIXED_CURRENCY_VENDOR);
    }

    public static function commissionUnconfigured(): self
    {
        return new self(self::COMMISSION_UNCONFIGURED);
    }

    public static function couponRejected(string $couponErrorCode): self
    {
        return new self(self::COUPON_REJECTED, couponErrorCode: $couponErrorCode);
    }
}
