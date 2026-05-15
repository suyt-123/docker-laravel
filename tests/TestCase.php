<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function authorizedUser(array $attributes = [], string $roleCode = 'admin'): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create($attributes);
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}
