<?php

namespace App\Policies;

use App\Models\Attribute;
use App\Models\User;

class AttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Attribute $attribute): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Attribute $attribute): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Attribute $attribute): bool
    {
        return false;
    }
}
