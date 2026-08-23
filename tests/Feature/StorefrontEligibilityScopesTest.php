<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontEligibilityScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_storefront_navigable_depths_and_inactive_ancestors(): void
    {
        $root = Category::factory()->create(['is_active' => true, 'parent_id' => null, 'slug' => 'root-a']);
        $child = Category::factory()->create(['is_active' => true, 'parent_id' => $root->id, 'slug' => 'child-a']);
        $leaf = Category::factory()->create(['is_active' => true, 'parent_id' => $child->id, 'slug' => 'leaf-a']);

        $this->assertTrue(Category::query()->storefrontNavigable()->whereKey($root->id)->exists());
        $this->assertTrue(Category::query()->storefrontNavigable()->whereKey($child->id)->exists());
        $this->assertTrue(Category::query()->storefrontNavigable()->whereKey($leaf->id)->exists());

        $inactiveParent = Category::factory()->create(['is_active' => false, 'parent_id' => null, 'slug' => 'inactive-root']);
        $orphanChild = Category::factory()->create(['is_active' => true, 'parent_id' => $inactiveParent->id, 'slug' => 'orphan-child']);
        $this->assertFalse(Category::query()->storefrontNavigable()->whereKey($orphanChild->id)->exists());

        $deepRoot = Category::factory()->create(['is_active' => true, 'parent_id' => null, 'slug' => 'deep-root']);
        $d2 = Category::factory()->create(['is_active' => true, 'parent_id' => $deepRoot->id, 'slug' => 'deep-2']);
        $d3 = Category::factory()->create(['is_active' => true, 'parent_id' => $d2->id, 'slug' => 'deep-3']);
        $d4 = Category::factory()->create(['is_active' => true, 'parent_id' => $d3->id, 'slug' => 'deep-4']);
        $this->assertFalse(Category::query()->storefrontNavigable()->whereKey($d4->id)->exists());
        $this->assertTrue(Category::query()->storefrontNavigable()->whereKey($d3->id)->exists());
    }

    public function test_store_publicly_eligible_requires_active_store_and_approved_vendor(): void
    {
        $eligibleVendor = $this->createVendorUser();
        $eligibleStore = $eligibleVendor->vendor->store;
        $this->assertTrue(Store::query()->publiclyEligible()->whereKey($eligibleStore->id)->exists());

        $eligibleStore->forceFill(['status' => StoreStatus::Suspended])->save();
        $this->assertFalse(Store::query()->publiclyEligible()->whereKey($eligibleStore->id)->exists());

        $eligibleStore->forceFill(['status' => StoreStatus::Active])->save();
        $eligibleVendor->vendor->forceFill(['status' => VendorStatus::Suspended])->save();
        $this->assertFalse(Store::query()->publiclyEligible()->whereKey($eligibleStore->id)->exists());
    }
}
