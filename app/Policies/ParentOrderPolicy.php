<?php

namespace App\Policies;

use App\Models\ParentOrder;
use App\Models\User;

class ParentOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCustomer() || $user->isStaff();
    }

    public function view(User $user, ParentOrder $parentOrder): bool
    {
        return $user->isStaff() || $parentOrder->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ParentOrder $parentOrder): bool
    {
        return false;
    }

    public function delete(User $user, ParentOrder $parentOrder): bool
    {
        return false;
    }
}
