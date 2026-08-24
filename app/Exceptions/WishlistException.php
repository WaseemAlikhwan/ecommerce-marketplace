<?php

namespace App\Exceptions;

use RuntimeException;

class WishlistException extends RuntimeException
{
    public const UNAUTHORIZED = 'unauthorized';

    public const NOT_FOUND = 'not_found';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public static function unauthorized(): self
    {
        return new self(self::UNAUTHORIZED);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND);
    }
}
