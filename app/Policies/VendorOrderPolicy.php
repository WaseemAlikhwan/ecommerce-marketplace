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

    public function delete(User $user, VendorOrder $vendorOrder): bool
    {
        return false;
    }
}
