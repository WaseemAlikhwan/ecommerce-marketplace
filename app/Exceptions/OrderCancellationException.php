<?php

namespace App\Exceptions;

use RuntimeException;

class OrderCancellationException extends RuntimeException
{
    public const UNAUTHORIZED = 'unauthorized';

    public const ILLEGAL_STATE = 'illegal_state';

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

    public static function illegalState(): self
    {
        return new self(self::ILLEGAL_STATE);
    }
}
