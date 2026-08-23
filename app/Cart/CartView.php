<?php

namespace App\Cart;

/**
 * Side-effect-free cart presentation state (C1-C).
 * No single converted grand total — only per-currency subtotals.
 */
final class CartView
{
    /**
     * @param  list<CartViewLine>  $lines
     * @param  list<CartCurrencySubtotal>  $subtotals
     */
    public function __construct(
        public readonly array $lines,
        public readonly array $subtotals,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * @return array{lines: list<array<string, mixed>>, subtotals: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'lines' => array_map(
                static fn (CartViewLine $line): array => $line->toArray(),
                $this->lines,
            ),
            'subtotals' => array_map(
                static fn (CartCurrencySubtotal $subtotal): array => $subtotal->toArray(),
                $this->subtotals,
            ),
        ];
    }
}
