<?php

namespace App\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\VendorOrder;
use InvalidArgumentException;

/**
 * V1 COD-only gateway: one pending Payment per Vendor Order.
 * Amount = items + shipping (Vendor Order grand total).
 */
final class CodPaymentGateway implements PaymentGateway
{
    public function chargeVendorOrder(VendorOrder $vendorOrder): Payment
    {
        if ($vendorOrder->payment()->exists()) {
            throw new InvalidArgumentException('Vendor order already has a payment.');
        }

        return Payment::query()->create([
            'vendor_order_id' => $vendorOrder->id,
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
            'currency_code' => $vendorOrder->currency_code,
            'amount_minor' => $vendorOrder->grand_total_amount_minor,
            'collected_at' => null,
        ]);
    }
}
