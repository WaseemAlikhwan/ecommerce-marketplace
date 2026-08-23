<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Models\VendorOrder;

interface PaymentGateway
{
    /**
     * Record payment for a Vendor Order. V1 COD driver creates a pending row.
     */
    public function chargeVendorOrder(VendorOrder $vendorOrder): Payment;
}
