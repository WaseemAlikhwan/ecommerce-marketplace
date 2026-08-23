<?php

namespace App\Checkout;

/**
 * Query-free parent order presentation for account Blade.
 */
final class ParentOrderView
{
    /**
     * @param  list<array{public_code: string, store_name: string, status: string, currency_code: string, items_subtotal_label: string, shipping_label: string, grand_total_label: string, payment_status: string, items: list<array{name: string, quantity: int, line_total_label: string}>}>  $vendorOrders
     * @param  list<array{currency_code: string, label: string}>  $codDues
     * @param  array{recipient_name: string, phone: string, lines: string, locality: string, country_code: string, notes: ?string}  $shipping
     */
    public function __construct(
        public readonly int $id,
        public readonly string $publicCode,
        public readonly string $status,
        public readonly string $placedAtLabel,
        public readonly array $shipping,
        public readonly array $vendorOrders,
        public readonly array $codDues,
    ) {}
}
