<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    /**
     * Parse a human-readable decimal string into integer minor units without floats.
     *
     * @throws InvalidArgumentException
     */
    public static function parseToMinor(string $input, int $exponent): int
    {
        $value = trim($input);

        if (str_starts_with($value, '-')) {
            throw new InvalidArgumentException(__('Negative amounts are not allowed.'));
        }

        if ($value === '' || ! preg_match('/^\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('The amount format is invalid.'));
        }

        if ($exponent < 0) {
            throw new InvalidArgumentException(__('The amount format is invalid.'));
        }

        if ($exponent === 0) {
            if (str_contains($value, '.')) {
                throw new InvalidArgumentException(__('Too many decimal places for this currency.'));
            }

            return self::digitsToInt($value);
        }

        if (! str_contains($value, '.')) {
            return self::digitsToInt($value.str_repeat('0', $exponent));
        }

        [$whole, $fraction] = explode('.', $value, 2);

        if (strlen($fraction) > $exponent) {
            throw new InvalidArgumentException(__('Too many decimal places for this currency.'));
        }

        $fraction = str_pad($fraction, $exponent, '0');

        return self::digitsToInt(($whole === '' ? '0' : $whole).$fraction);
    }

    public static function formatFromMinor(int $minor, int $exponent): string
    {
        if ($minor < 0) {
            throw new InvalidArgumentException(__('Negative amounts are not allowed.'));
        }

        if ($exponent === 0) {
            return (string) $minor;
        }

        $scale = (int) ('1'.str_repeat('0', $exponent));
        $whole = intdiv($minor, $scale);
        $fraction = $minor % $scale;

        return sprintf('%d.%0'.$exponent.'d', $whole, $fraction);
    }

    private static function digitsToInt(string $digits): int
    {
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;

        if (! ctype_digit($digits)) {
            throw new InvalidArgumentException(__('The amount format is invalid.'));
        }

        $max = (string) PHP_INT_MAX;

        if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && $digits > $max)) {
            throw new InvalidArgumentException(__('The amount is too large.'));
        }

        return (int) $digits;
    }
}
