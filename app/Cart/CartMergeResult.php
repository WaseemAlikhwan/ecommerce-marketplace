<?php

namespace App\Cart;

final class CartMergeResult
{
    public const FLASH_KEY = 'cart.merge';

    /**
     * @param  list<CartLine>  $kept
     * @param  list<CartMergeAdjustment>  $adjusted
     * @param  list<CartMergeUnavailable>  $unavailable
     */
    public function __construct(
        public readonly array $kept,
        public readonly array $adjusted,
        public readonly array $unavailable,
    ) {}

    public function isEmpty(): bool
    {
        return $this->kept === [] && $this->adjusted === [] && $this->unavailable === [];
    }

    /**
     * JSON-safe flash payload for C1-D UI consumption.
     *
     * @return array{
     *     kept: list<array{variant_id: int, quantity: int}>,
     *     adjusted: list<array{variant_id: int, from_quantity: int, to_quantity: int}>,
     *     unavailable: list<array{variant_id: int, reason: string}>
     * }
     */
    public function toFlashPayload(): array
    {
        return [
            'kept' => array_map(
                static fn (CartLine $line): array => [
                    'variant_id' => $line->variantId,
                    'quantity' => $line->quantity,
                ],
                $this->kept,
            ),
            'adjusted' => array_map(
                static fn (CartMergeAdjustment $line): array => $line->toArray(),
                $this->adjusted,
            ),
            'unavailable' => array_map(
                static fn (CartMergeUnavailable $line): array => $line->toArray(),
                $this->unavailable,
            ),
        ];
    }
}
