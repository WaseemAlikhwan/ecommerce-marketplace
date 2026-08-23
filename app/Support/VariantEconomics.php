<?php

namespace App\Support;

use App\Models\ProductVariant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class VariantEconomics
{
    public static function normalizeSku(string $sku): string
    {
        $normalized = strtoupper(trim($sku));

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'sku' => __('The SKU field is required.'),
            ]);
        }

        return $normalized;
    }

    public static function assertSkuAvailable(int $storeId, string $sku, ?int $ignoreVariantId = null): void
    {
        $query = ProductVariant::withTrashed()
            ->where('store_id', $storeId)
            ->where('sku', $sku);

        if ($ignoreVariantId !== null) {
            $query->where('id', '!=', $ignoreVariantId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'sku' => __('This SKU is already used in your store.'),
            ]);
        }
    }

    public static function parsePrice(string $price, int $exponent, string $field = 'price'): int
    {
        try {
            $minor = Money::parseToMinor($price, $exponent);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                $field => $e->getMessage(),
            ]);
        }

        if ($minor <= 0) {
            throw ValidationException::withMessages([
                $field => __('Price must be greater than zero.'),
            ]);
        }

        return $minor;
    }

    public static function parseOptionalCompareAt(?string $compareAt, int $exponent, int $priceMinor, string $field = 'compare_at_price'): ?int
    {
        if ($compareAt === null || trim($compareAt) === '') {
            return null;
        }

        try {
            $minor = Money::parseToMinor($compareAt, $exponent);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                $field => $e->getMessage(),
            ]);
        }

        if ($minor <= $priceMinor) {
            throw ValidationException::withMessages([
                $field => __('Compare-at price must be greater than the selling price.'),
            ]);
        }

        return $minor;
    }

    public static function normalizeQuantity(mixed $quantity, string $field = 'quantity'): int
    {
        if (is_int($quantity)) {
            if ($quantity < 0) {
                throw ValidationException::withMessages([
                    $field => __('Quantity must be a whole number greater than or equal to zero.'),
                ]);
            }

            $raw = (string) $quantity;
        } elseif (is_string($quantity)) {
            $raw = trim($quantity);
        } else {
            $raw = '';
        }

        if ($raw === '' || ! preg_match('/^\d+$/', $raw)) {
            throw ValidationException::withMessages([
                $field => __('Quantity must be a whole number greater than or equal to zero.'),
            ]);
        }

        $raw = ltrim($raw, '0');
        $raw = $raw === '' ? '0' : $raw;
        $max = (string) ProductVariant::MAX_QUANTITY;

        if (strlen($raw) > strlen($max) || (strlen($raw) === strlen($max) && $raw > $max)) {
            throw ValidationException::withMessages([
                $field => __('Quantity may not be greater than :max.', ['max' => ProductVariant::MAX_QUANTITY]),
            ]);
        }

        return (int) $raw;
    }

    public static function rethrowUniqueConstraint(UniqueConstraintViolationException $exception): never
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'sku')) {
            throw ValidationException::withMessages([
                'sku' => __('This SKU is already used in your store.'),
            ]);
        }

        if (str_contains($message, 'combination_key') || str_contains($message, 'combination')) {
            throw ValidationException::withMessages([
                'variants' => __('This variant combination already exists.'),
            ]);
        }

        throw $exception;
    }
}
