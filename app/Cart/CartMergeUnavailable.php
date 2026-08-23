<?php

namespace App\Cart;

final class CartMergeUnavailable
{
    public const MISSING = 'missing';

    public const NOT_PURCHASABLE = 'not_purchasable';

    public const OUT_OF_STOCK = 'out_of_stock';

    public function __construct(
        public readonly int $variantId,
        public readonly string $reason,
    ) {}

    /**
     * @return array{variant_id: int, reason: string}
     */
    public function toArray(): array
    {
        return [
            'variant_id' => $this->variantId,
            'reason' => $this->reason,
        ];
    }
}
