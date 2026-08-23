<?php

namespace App\Policies;

use App\Models\AttributeValue;
use App\Models\User;

class AttributeValuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, AttributeValue $attributeValue): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, AttributeValue $attributeValue): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, AttributeValue $attributeValue): bool
    {
        return false;
    }
}
