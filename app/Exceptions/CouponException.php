<?php

namespace App\Exceptions;

use RuntimeException;

class CouponException extends RuntimeException
{
    public const NOT_FOUND = 'not_found';

    public const INACTIVE = 'inactive';

    public const EXPIRED = 'expired';

    public const CURRENCY = 'currency';

    public const MIN_NOT_MET = 'min_not_met';

    public const LIMIT = 'limit';

    public const CONFLICT = 'conflict';

    public const INVALID = 'invalid';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND);
    }

    public static function inactive(): self
    {
        return new self(self::INACTIVE);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
    }

    public static function currency(): self
    {
        return new self(self::CURRENCY);
    }

    public static function minNotMet(): self
    {
        return new self(self::MIN_NOT_MET);
    }

    public static function limit(): self
    {
        return new self(self::LIMIT);
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
