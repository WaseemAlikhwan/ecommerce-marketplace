<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorOrder;

class VendorOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessVendorPanel() || $user->isCustomer() || $user->isStaff();
    }

    public function view(User $user, VendorOrder $vendorOrder): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        if ($user->canAccessVendorPanel() && $user->vendor?->id === $vendorOrder->vendor_id) {
            return true;
        }

        return $vendorOrder->parentOrder?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, VendorOrder $vendorOrder): bool
    {
        return false;
    }

    /**
     * VOL-A: vendor may advance fulfillment on own Vendor Order only.
     */
    public function advance(User $user, VendorOrder $vendorOrder): bool
    {
        return $user->canAccessVendorPanel()
            && $user->vendor !== null
            && (int) $user->vendor->id === (int) $vendorOrder->vendor_id;
    }

    /**
     * CAN-A: vendor may cancel own Vendor Order (eligibility enforced in service).
     */
    public function cancel(User $user, VendorOrder $vendorOrder): bool
    {
        return $user->canAccessVendorPanel()
            && $user->vendor !== null
            && (int) $user->vendor->id === (int) $vendorOrder->vendor_id;
    }

    public function delete(User $user, VendorOrder $vendorOrder): bool
    {
        return false;
    }
}
