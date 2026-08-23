<?php

namespace App\Support;

use InvalidArgumentException;

final class CombinationKey
{
    public const SIMPLE = 'default';

    /**
     * @param  array<int, int>  $attributeIdToValueId
     */
    public static function forVariable(array $attributeIdToValueId): string
    {
        if ($attributeIdToValueId === []) {
            throw new InvalidArgumentException('A variable combination requires at least one attribute value.');
        }

        ksort($attributeIdToValueId, SORT_NUMERIC);

        $parts = [];

        foreach ($attributeIdToValueId as $attributeId => $valueId) {
            if (! is_int($attributeId) || ! is_int($valueId) || $attributeId < 1 || $valueId < 1) {
                throw new InvalidArgumentException('Combination keys require positive integer attribute and value IDs.');
            }

            $parts[] = 'a'.$attributeId.':v'.$valueId;
        }

        $key = implode('|', $parts);

        if ($key === self::SIMPLE) {
            throw new InvalidArgumentException('The literal default combination key is reserved for simple products.');
        }

        return $key;
    }
}
