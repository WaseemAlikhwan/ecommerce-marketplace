<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAssignRoleLoadedRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_role_invalidates_loaded_roles_so_has_role_sees_new_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::CUSTOMER);
        $user->load('roles');

        $this->assertTrue($user->relationLoaded('roles'));
        $this->assertFalse($user->hasRole(Role::VENDOR));

        $user->assignRole(Role::VENDOR);

        $this->assertTrue($user->hasRole(Role::VENDOR));
        $this->assertTrue($user->roles->contains(fn (Role $role): bool => $role->name === Role::VENDOR));
    }
}
