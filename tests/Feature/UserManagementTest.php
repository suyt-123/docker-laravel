<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Auth\CapabilityAuthorizer;
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
}
