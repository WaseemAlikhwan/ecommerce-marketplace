<?php

namespace App\Exceptions;

use RuntimeException;

class CartException extends RuntimeException
{
    public const VARIANT_UNAVAILABLE = 'variant_unavailable';

    public const INVALID_QUANTITY = 'invalid_quantity';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public static function unavailable(): self
    {
        return new self(self::VARIANT_UNAVAILABLE);
    }

    public static function invalidQuantity(): self
    {
        return new self(self::INVALID_QUANTITY);
    }
}
