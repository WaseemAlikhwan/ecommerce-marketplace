<?php

namespace App\Exceptions;

use RuntimeException;

class VendorOrderLifecycleException extends RuntimeException
{
    public const UNAUTHORIZED = 'unauthorized';

    public const ILLEGAL_TRANSITION = 'illegal_transition';

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

    public static function illegalTransition(): self
    {
        return new self(self::ILLEGAL_TRANSITION);
    }
}
