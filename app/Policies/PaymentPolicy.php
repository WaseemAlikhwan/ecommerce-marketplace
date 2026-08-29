<?php

namespace App\Policies;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessVendorPanel() || $user->isCustomer() || $user->isStaff();
    }

    public function view(User $user, Payment $payment): bool
    {
        $vendorOrder = $payment->vendorOrder;

        if ($vendorOrder === null) {
            return false;
        }

        if ($user->isStaff()) {
            return true;
        }

        if ($user->canAccessVendorPanel() && $user->vendor?->id === $vendorOrder->vendor_id) {
            return true;
        }

        return $vendorOrder->parentOrder?->user_id === $user->id;
    }

    public function collect(User $user, Payment $payment): bool
    {
        if (! $this->isCollectable($payment)) {
            return false;
        }

        if ($user->isStaff()) {
            return true;
        }

        $vendorOrder = $payment->vendorOrder;
        if ($vendorOrder === null) {
            return false;
        }

        return $user->canAccessVendorPanel()
            && $user->vendor?->id === $vendorOrder->vendor_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    private function isCollectable(Payment $payment): bool
    {
        if ($payment->method !== PaymentMethod::Cod) {
            return false;
        }

        if ($payment->status !== PaymentStatus::Pending) {
            return false;
        }

        $payment->loadMissing('vendorOrder');
        $vendorOrder = $payment->vendorOrder;

        return $vendorOrder !== null
            && $vendorOrder->status === VendorOrderStatus::Delivered;
    }
}
