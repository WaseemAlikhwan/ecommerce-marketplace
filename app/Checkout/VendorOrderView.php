<?php

namespace App\Checkout;

/**
 * Query-free vendor order presentation for vendor Blade.
 */
final class VendorOrderView
{
    /**
     * @param  list<array{name: string, quantity: int, unit_price_label: string, line_total_label: string}>  $items
     * @param  array{recipient_name: string, phone: string, lines: string, locality: string, country_code: string, notes: ?string}  $shipping
     */
    public function __construct(
        public readonly int $id,
        public readonly string $publicCode,
        public readonly string $parentPublicCode,
        public readonly string $status,
        public readonly string $currencyCode,
        public readonly string $itemsSubtotalLabel,
        public readonly string $shippingLabel,
        public readonly string $grandTotalLabel,
        public readonly string $paymentStatus,
        public readonly string $paymentMethod,
        public readonly array $shipping,
        public readonly array $items,
        public readonly string $placedAtLabel,
    ) {}
}
