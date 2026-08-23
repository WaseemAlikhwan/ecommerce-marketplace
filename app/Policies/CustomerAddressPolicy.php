<?php

namespace App\Policies;

use App\Models\CustomerAddress;
use App\Models\User;

class CustomerAddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCustomer() || $user->isStaff();
    }

    public function view(User $user, CustomerAddress $address): bool
    {
        return $user->isStaff() || $address->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isCustomer() || $user->isStaff();
    }

    public function update(User $user, CustomerAddress $address): bool
    {
        return $address->user_id === $user->id;
    }

    public function delete(User $user, CustomerAddress $address): bool
    {
        return $address->user_id === $user->id;
    }
}
