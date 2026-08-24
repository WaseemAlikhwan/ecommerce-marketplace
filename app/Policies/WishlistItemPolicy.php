<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WishlistItem;

class WishlistItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCustomer();
    }

    public function view(User $user, WishlistItem $wishlistItem): bool
    {
        return $user->isCustomer()
            && (int) $user->id === (int) $wishlistItem->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function delete(User $user, WishlistItem $wishlistItem): bool
    {
        return $user->isCustomer()
            && (int) $user->id === (int) $wishlistItem->user_id;
    }
}
