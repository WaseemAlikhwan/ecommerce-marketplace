<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Guards public presentation boundaries against accidental ORM serialization.
 */
final class ModelFreePayload
{
    public static function assert(mixed $value, string $context): void
    {
        if ($value instanceof Model || $value instanceof Collection) {
            throw new LogicException("{$context} accepts plain presentation arrays only.");
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $nested) {
            self::assert($nested, $context);
        }
    }
}
