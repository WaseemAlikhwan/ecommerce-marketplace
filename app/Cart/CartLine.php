<?php

namespace App\Cart;

final class CartLine
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $quantity,
    ) {}
}
