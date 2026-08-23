<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed marketplace roles (Phase 1 foundation; Phase 2 expands authz).
     */
    public function run(): void
    {
        foreach (Role::ALL as $role) {
            Role::query()->firstOrCreate(['name' => $role]);
        }
    }
}
