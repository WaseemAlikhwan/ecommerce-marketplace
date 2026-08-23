<?php

/**
 * Platform default flat shipping fees per currency (integer minor units).
 * Store override: stores.flat_shipping_amount_minor (nullable → fall back here).
 * Do not hard-code fee amounts inside ShippingCalculator implementations.
 */
return [
    'flat_fee_defaults_minor' => [
        'SYP' => (int) env('SHIPPING_FLAT_DEFAULT_SYP_MINOR', 0),
        'USD' => (int) env('SHIPPING_FLAT_DEFAULT_USD_MINOR', 0),
    ],
];
