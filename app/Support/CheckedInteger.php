<?php

namespace App\Support;

use OverflowException as PhpOverflowException;

/**
 * Overflow-checked integer arithmetic for money and quantity math.
 */
final class CheckedInteger
{
    public static function multiply(int $left, int $right): int
    {
        if ($left === 0 || $right === 0) {
            return 0;
        }

        if ($left === 1) {
            return $right;
        }

        if ($right === 1) {
            return $left;
        }

        if ($left === PHP_INT_MIN || $right === PHP_INT_MIN) {
            throw new PhpOverflowException('Integer multiplication overflow.');
        }

        $negative = ($left < 0) xor ($right < 0);
        $a = abs($left);
        $b = abs($right);

        if ($a > intdiv(PHP_INT_MAX, $b)) {
            throw new PhpOverflowException('Integer multiplication overflow.');
        }

        $product = $a * $b;

        return $negative ? -$product : $product;
    }

    public static function add(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new PhpOverflowException('Integer addition overflow.');
        }

        if ($right < 0 && $left < PHP_INT_MIN - $right) {
            throw new PhpOverflowException('Integer addition overflow.');
        }

        return $left + $right;
    }
}
