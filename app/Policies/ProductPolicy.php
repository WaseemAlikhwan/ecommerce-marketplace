<?php

namespace App\Policies;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessVendorPanel() && $user->vendor?->store !== null;
    }

    public function view(User $user, Product $product): bool
    {
        return $this->ownsStoreProduct($user, $product);
    }

    public function create(User $user): bool
    {
        if ($user->isStaff()) {
            return false;
        }

        return $user->canAccessVendorPanel() && $user->vendor?->store !== null;
    }

    public function update(User $user, Product $product): bool
    {
        if (! $this->ownsStoreProduct($user, $product)) {
            return false;
        }

        return $product->status->isVendorEditable();
    }

    public function archive(User $user, Product $product): bool
    {
        if (! $this->ownsStoreProduct($user, $product)) {
            return false;
        }

        return $product->status !== ProductStatus::Suspended
            && $product->status !== ProductStatus::Archived;
    }

    public function publish(User $user, Product $product): bool
    {
        if ($user->isStaff()) {
            return false;
        }

        if (! $this->ownsStoreProduct($user, $product)) {
            return false;
        }

        if ($product->trashed()) {
            return false;
        }

        return in_array($product->status, [
            ProductStatus::Draft,
            ProductStatus::Unpublished,
            ProductStatus::Published,
        ], true);
    }

    public function unpublish(User $user, Product $product): bool
    {
        if ($user->isStaff()) {
            return false;
        }

        if (! $this->ownsStoreProduct($user, $product)) {
            return false;
        }

        if ($product->trashed()) {
            return false;
        }

        return in_array($product->status, [
            ProductStatus::Published,
            ProductStatus::Unpublished,
        ], true);
    }

    public function delete(User $user, Product $product): bool
    {
        return false;
    }

    private function ownsStoreProduct(User $user, Product $product): bool
    {
        return $user->canAccessVendorPanel()
            && $user->vendor?->store?->id === $product->store_id;
    }
}
