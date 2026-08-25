<?php

namespace App\Exceptions;

use RuntimeException;

class ReviewException extends RuntimeException
{
    public const UNAUTHORIZED = 'unauthorized';

    public const NOT_FOUND = 'not_found';

    public const INELIGIBLE = 'ineligible';

    public const CONFLICT = 'conflict';

    public const INVALID = 'invalid';

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

    public static function ineligible(): self
    {
        return new self(self::INELIGIBLE);
    }

    public static function conflict(): self
    {
        return new self(self::CONFLICT);
    }

    public static function invalid(): self
    {
        return new self(self::INVALID);
    }
}
