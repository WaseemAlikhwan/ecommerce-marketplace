<?php

namespace App\Checkout;

use App\Models\ParentOrder;

final class PlaceOrderResult
{
    /**
     * @param  array<string, int>  $codDuesMinorByCurrency  currency code → COD due in minor units
     */
    public function __construct(
        public readonly ParentOrder $parentOrder,
        public readonly array $codDuesMinorByCurrency,
    ) {}
}
