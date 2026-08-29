<?php

namespace App\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Exceptions\PaymentCollectionException;
use App\Models\Payment;
use App\Models\VendorOrder;
use Illuminate\Support\Facades\DB;
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

    public function markCollected(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing('vendorOrder');

            if ($locked->method !== PaymentMethod::Cod) {
                throw PaymentCollectionException::illegalState();
            }

            if ($locked->status !== PaymentStatus::Pending) {
                throw PaymentCollectionException::illegalState();
            }

            $vendorOrder = $locked->vendorOrder;
            if ($vendorOrder === null || $vendorOrder->status !== VendorOrderStatus::Delivered) {
                throw PaymentCollectionException::illegalState();
            }

            $locked->status = PaymentStatus::Collected;
            $locked->collected_at = now();
            $locked->save();

            return $locked->fresh(['vendorOrder', 'currency']);
        });
    }
}
