<?php

namespace App\Cart;

final class CartMergeAdjustment
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $fromQuantity,
        public readonly int $toQuantity,
    ) {}

    /**
     * @return array{variant_id: int, from_quantity: int, to_quantity: int}
     */
    public function toArray(): array
    {
        return [
            'variant_id' => $this->variantId,
            'from_quantity' => $this->fromQuantity,
            'to_quantity' => $this->toQuantity,
        ];
    }
}
