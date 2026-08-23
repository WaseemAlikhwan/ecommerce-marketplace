<?php

namespace App\Cart;

use App\Exceptions\CartException;

/**
 * Maps cart machine error codes to localized storefront messages (C1-D1).
 */
final class CartExceptionTranslator
{
    public static function message(CartException $exception): string
    {
        return match ($exception->errorCode) {
            CartException::VARIANT_UNAVAILABLE => __('This product is unavailable.'),
            CartException::INVALID_QUANTITY => __('The selected quantity is invalid.'),
            default => __('Unable to update your cart.'),
        };
    }
}
