<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed a Super Admin when credentials are provided via environment.
     */
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');
        $phone = env('SUPER_ADMIN_PHONE', '+963900000000');

        if (! filled($email) || ! filled($password)) {
            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                'phone' => $phone,
                'password' => Hash::make($password),
                'preferred_locale' => 'ar',
                'email_verified_at' => now(),
            ]
        );

        $user->assignRole(Role::SUPER_ADMIN);
    }
}
