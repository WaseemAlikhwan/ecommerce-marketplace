<?php

namespace App\Policies;

use App\Models\User;

/**
 * Staff-only fail-closed users overview (ADM-C). No role assignment UI.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isStaff();
    }
}
