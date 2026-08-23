<?php

namespace App\Contracts;

use App\Models\Store;
use App\Models\Vendor;

interface ShippingCalculator
{
    /**
     * Flat shipping fee in minor units for the Vendor Order currency.
     * Store override wins; otherwise platform defaults from config/shipping.php.
     */
    public function feeForVendorOrder(Vendor $vendor, Store $store, string $currencyCode): int;
}
