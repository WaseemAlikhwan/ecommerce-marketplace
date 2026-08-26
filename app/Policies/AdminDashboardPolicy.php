<?php

namespace App\Policies;

use App\Models\User;

/**
 * Staff-only fail-closed authorization for admin dashboard / KPI ops (ADM-A).
 *
 * Used with Gate::authorize('viewAny', AdminDashboardStats::class) (and later HTTP).
 * No granular permission catalog (BR-PERM-07 out of ADM V1).
 */
class AdminDashboardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, mixed $adminDashboardStats = null): bool
    {
        return $user->isStaff();
    }
}
