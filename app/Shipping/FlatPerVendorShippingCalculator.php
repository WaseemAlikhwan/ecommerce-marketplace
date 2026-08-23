<?php

namespace App\Shipping;

use App\Contracts\ShippingCalculator;
use App\Models\Store;
use App\Models\Vendor;

/**
 * V1 flat-per-vendor shipping: store.flat_shipping_amount_minor, else config defaults.
 * Fee amounts live in DB/config only — never hard-coded here.
 */
final class FlatPerVendorShippingCalculator implements ShippingCalculator
{
    public function feeForVendorOrder(Vendor $vendor, Store $store, string $currencyCode): int
    {
        if ($store->flat_shipping_amount_minor !== null) {
            return max(0, (int) $store->flat_shipping_amount_minor);
        }

        $code = strtoupper($currencyCode);
        $defaults = config('shipping.flat_fee_defaults_minor', []);

        if (! is_array($defaults)) {
            return 0;
        }

        return max(0, (int) ($defaults[$code] ?? 0));
    }
}
