<?php

namespace App\Policies;

use App\Models\ProductReview;
use App\Models\User;

class ProductReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCustomer() || $user->isStaff();
    }

    public function view(User $user, ProductReview $productReview): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        if ($productReview->isApproved()) {
            return true;
        }

        return $user->isCustomer()
            && (int) $user->id === (int) $productReview->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function update(User $user, ProductReview $productReview): bool
    {
        return $user->isCustomer()
            && (int) $user->id === (int) $productReview->user_id;
    }

    public function moderate(User $user, ProductReview $productReview): bool
    {
        return $user->isStaff();
    }
}
