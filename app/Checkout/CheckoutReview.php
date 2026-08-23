<?php

namespace App\Checkout;

use App\Cart\CartViewLine;

/**
 * Query-free checkout review state for Blade.
 *
 * @phpstan-type MoneyPayload array{currency_code: string, exponent: int, amount_minor: string, label: string}
 */
final class CheckoutReview
{
    /**
     * @param  list<CartViewLine>  $lines
     * @param  list<array{store_name: string, currency_code: string, items_subtotal: MoneyPayload, shipping: MoneyPayload, due: MoneyPayload}>  $vendorGroups
     * @param  list<MoneyPayload>  $codDues
     * @param  list<array{id: int, label: string, recipient_name: string, phone: string, summary: string, is_default: bool}>  $addresses
     * @param  list<array{id: int, name: string, cities: list<array{id: int, name: string}>}>  $governorates
     */
    public function __construct(
        public readonly array $lines,
        public readonly array $vendorGroups,
        public readonly array $codDues,
        public readonly array $addresses,
        public readonly array $governorates,
        public readonly bool $hasPayableLines,
        public readonly ?int $defaultAddressId,
    ) {}
}
