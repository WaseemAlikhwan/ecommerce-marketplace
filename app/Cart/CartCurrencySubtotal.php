<?php

namespace App\Cart;

/**
 * @phpstan-type MoneyPayload array{currency_code: string, exponent: int, amount_minor: string}
 */
final class CartCurrencySubtotal
{
    /**
     * @param  MoneyPayload  $total
     */
    public function __construct(
        public readonly string $currencyCode,
        public readonly int $currencyExponent,
        public readonly int $amountMinor,
        public readonly array $total,
    ) {}

    /**
     * @return array{currency_code: string, currency_exponent: int, amount_minor: string, total: array{currency_code: string, exponent: int, amount_minor: string}}
     */
    public function toArray(): array
    {
        return [
            'currency_code' => $this->currencyCode,
            'currency_exponent' => $this->currencyExponent,
            'amount_minor' => (string) $this->amountMinor,
            'total' => $this->total,
        ];
    }
}
