<?php

namespace App\Support;

/**
 * Shared read-side equivalent of Laravel's `alpha_dash:ascii` rule.
 */
final class AsciiSlug
{
    public const MAX_LENGTH = 120;

    public const PATTERN = '/\A[a-zA-Z0-9_-]+\z/D';

    public static function isValid(string $value, int $maxLength = self::MAX_LENGTH): bool
    {
        return $value !== ''
            && $maxLength > 0
            && mb_strlen($value) <= $maxLength
            && preg_match(self::PATTERN, $value) === 1;
    }
}
