<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorApplication;

class VendorApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, VendorApplication $vendorApplication): bool
    {
        return $user->isStaff() || $vendorApplication->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && ! $user->isVendor()
            && $user->vendor()->doesntExist();
    }

    public function approve(User $user, VendorApplication $vendorApplication): bool
    {
        return $user->isStaff();
    }

    public function reject(User $user, VendorApplication $vendorApplication): bool
    {
        return $user->isStaff();
    }
}
