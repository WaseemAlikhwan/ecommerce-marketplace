<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    public function view(User $user, Store $store): bool
    {
        return $user->isStaff() || $user->vendor?->id === $store->vendor_id;
    }

    public function update(User $user, Store $store): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->canAccessVendorPanel() && $user->vendor?->id === $store->vendor_id;
    }
}
