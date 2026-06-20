<?php

namespace Tests\Feature;

use App\Auth\CapabilityAuthorizer;
use App\Models\Capability;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_user_pages(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_user_index(): void
    {
        $admin = $this->adminUser(['name' => '管理者']);

        $this
            ->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Users/Index')
                ->has('users.data', 1)
            );
    }

    public function test_authenticated_user_can_create_user_with_roles(): void
    {
        $admin = $this->adminUser();
        $accounting = Role::where('code', 'accounting')->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('users.store'), [
                'name' => '會計王小姐',
                'email' => 'accounting@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'email_verified' => true,
                'roles' => [$accounting->id],
            ])
            ->assertRedirect();

        $user = User::where('email', 'accounting@example.com')->firstOrFail();

        $this->assertSame('會計王小姐', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'role_id' => $accounting->id,
        ]);
    }

    public function test_user_requires_name_email_and_password(): void
    {
        $admin = $this->adminUser();

        $this
            ->actingAs($admin)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => '',
                'email' => '',
                'password' => '',
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_authenticated_user_can_update_user_and_reset_password(): void
    {
        $admin = $this->adminUser();
        $user = User::factory()->create([
            'name' => '原本姓名',
            'email' => 'old@example.com',
            'email_verified_at' => null,
        ]);
        $siteManager = Role::where('code', 'site_manager')->firstOrFail();

        $this
            ->actingAs($admin)
            ->patch(route('users.update', $user), [
                'name' => '工地主任林先生',
                'email' => 'manager@example.com',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
                'email_verified' => true,
                'roles' => [$siteManager->id],
            ])
            ->assertRedirect(route('users.show', $user));

        $user->refresh();

        $this->assertSame('工地主任林先生', $user->name);
        $this->assertSame('manager@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertTrue(app(CapabilityAuthorizer::class)->allows($user, 'field.dispatches.view.tenant'));
    }

    public function test_authenticated_user_can_delete_another_user_but_not_self(): void
    {
        $admin = $this->adminUser();
        $target = User::factory()->create();

        $this
            ->actingAs($admin)
            ->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);

        $this
            ->actingAs($admin)
            ->delete(route('users.destroy', $admin))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_user_without_capability_cannot_manage_users(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_user_manager_without_role_assignment_capability_cannot_assign_roles(): void
    {
        $this->seed(RbacSeeder::class);

        $manager = User::factory()->create();
        $target = User::factory()->create([
            'name' => '待更新使用者',
            'email' => 'target@example.com',
        ]);
        $userManagerRole = Role::create([
            'name' => 'User Manager',
            'code' => 'user_manager',
        ]);
        $userManagerRole->capabilities()->sync(
            \App\Models\Capability::query()
                ->whereIn('code', [
                    'security.users.view.tenant',
                    'security.users.update.tenant',
                ])
                ->pluck('id'),
        );
        $manager->roles()->attach($userManagerRole);
        $adminRole = Role::where('code', 'admin')->firstOrFail();

        $this
            ->actingAs($manager)
            ->patch(route('users.update', $target), [
                'name' => '待更新使用者',
                'email' => 'target@example.com',
                'email_verified' => false,
                'roles' => [$adminRole->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('role_user', [
            'user_id' => $target->id,
            'role_id' => $adminRole->id,
        ]);
    }

    public function test_user_manager_without_role_assignment_capability_cannot_assign_roles_on_create(): void
    {
        $this->seed(RbacSeeder::class);

        $manager = User::factory()->create();
        $userManagerRole = $this->customRoleWithCapabilities([
            'security.users.view.tenant',
            'security.users.create.tenant',
        ]);
        $manager->roles()->attach($userManagerRole);
        $adminRole = Role::where('code', 'admin')->firstOrFail();

        $this
            ->actingAs($manager)
            ->post(route('users.store'), [
                'name' => '新管理員',
                'email' => 'new-admin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'email_verified' => false,
                'roles' => [$adminRole->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'new-admin@example.com',
        ]);
    }

    public function test_user_manager_without_role_assignment_capability_can_update_profile_fields_when_roles_do_not_change(): void
    {
        $this->seed(RbacSeeder::class);

        $manager = User::factory()->create();
        $target = User::factory()->create([
            'name' => '原姓名',
            'email' => 'profile-target@example.com',
        ]);
        $workerRole = Role::where('code', 'worker')->firstOrFail();
        $target->roles()->attach($workerRole);
        $userManagerRole = $this->customRoleWithCapabilities([
            'security.users.view.tenant',
            'security.users.update.tenant',
        ]);
        $manager->roles()->attach($userManagerRole);

        $this
            ->actingAs($manager)
            ->patch(route('users.update', $target), [
                'name' => '新姓名',
                'email' => 'profile-target-updated@example.com',
                'email_verified' => false,
                'roles' => [$workerRole->id],
            ])
            ->assertRedirect(route('users.show', $target));

        $target->refresh();

        $this->assertSame('新姓名', $target->name);
        $this->assertSame('profile-target-updated@example.com', $target->email);
        $this->assertTrue($target->roles()->whereKey($workerRole->id)->exists());
    }

    public function test_user_manager_without_role_assignment_capability_cannot_remove_or_replace_roles(): void
    {
        $this->seed(RbacSeeder::class);

        $manager = User::factory()->create();
        $target = User::factory()->create([
            'name' => '角色異動目標',
            'email' => 'role-change-target@example.com',
        ]);
        $workerRole = Role::where('code', 'worker')->firstOrFail();
        $accountingRole = Role::where('code', 'accounting')->firstOrFail();
        $target->roles()->attach($workerRole);
        $userManagerRole = $this->customRoleWithCapabilities([
            'security.users.view.tenant',
            'security.users.update.tenant',
        ]);
        $manager->roles()->attach($userManagerRole);

        $this
            ->actingAs($manager)
            ->patch(route('users.update', $target), [
                'name' => '角色異動目標',
                'email' => 'role-change-target@example.com',
                'email_verified' => false,
                'roles' => [],
            ])
            ->assertForbidden();

        $this
            ->actingAs($manager)
            ->patch(route('users.update', $target), [
                'name' => '角色異動目標',
                'email' => 'role-change-target@example.com',
                'email_verified' => false,
                'roles' => [$accountingRole->id],
            ])
            ->assertForbidden();

        $target->refresh();

        $this->assertTrue($target->roles()->whereKey($workerRole->id)->exists());
        $this->assertFalse($target->roles()->whereKey($accountingRole->id)->exists());
    }

    public function test_admin_can_submit_duplicate_role_ids_without_duplicate_assignments(): void
    {
        $admin = $this->adminUser();
        $target = User::factory()->create([
            'name' => '重複角色測試',
            'email' => 'duplicate-role@example.com',
        ]);
        $workerRole = Role::where('code', 'worker')->firstOrFail();

        $this
            ->actingAs($admin)
            ->patch(route('users.update', $target), [
                'name' => '重複角色測試',
                'email' => 'duplicate-role@example.com',
                'email_verified' => false,
                'roles' => [$workerRole->id, $workerRole->id],
            ])
            ->assertRedirect(route('users.show', $target));

        $this->assertSame(1, $target->roles()->whereKey($workerRole->id)->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function adminUser(array $attributes = []): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create($attributes);
        $role = Role::where('code', 'admin')->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }

    /**
     * @param  array<int, string>  $capabilityCodes
     */
    private function customRoleWithCapabilities(array $capabilityCodes): Role
    {
        $role = Role::create([
            'name' => 'Scoped User Manager '.str()->random(8),
            'code' => 'scoped_user_manager_'.str()->random(8),
        ]);

        $role->capabilities()->sync(
            Capability::query()
                ->whereIn('code', $capabilityCodes)
                ->pluck('id'),
        );

        return $role;
    }
}
