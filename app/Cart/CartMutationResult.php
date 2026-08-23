<?php

namespace App\Cart;

final class CartMutationResult
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $quantity,
        public readonly bool $adjusted,
    ) {}
}
