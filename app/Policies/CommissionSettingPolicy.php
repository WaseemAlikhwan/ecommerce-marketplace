<?php

namespace App\Policies;

use App\Models\CommissionSetting;
use App\Models\User;

/**
 * Staff-only fail-closed global commission read (ADM-C). No override CRUD.
 */
class CommissionSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, CommissionSetting $commissionSetting): bool
    {
        return $user->isStaff();
    }
}
