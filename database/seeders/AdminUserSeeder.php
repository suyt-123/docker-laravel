<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the default development administrator account.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        $adminRole = Role::where('code', 'admin')->first();

        if ($adminRole) {
            $user->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}
