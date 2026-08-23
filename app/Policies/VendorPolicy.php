<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function view(User $user, Vendor $vendor): bool
    {
        return $user->isStaff() || $user->id === $vendor->user_id;
    }

    public function accessPanel(User $user, Vendor $vendor): bool
    {
        return $user->id === $vendor->user_id && $vendor->canAccessPanel();
    }
}
