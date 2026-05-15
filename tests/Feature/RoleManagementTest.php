<?php

namespace Tests\Feature;

use App\Auth\CapabilityAuthorizer;
use App\Models\Capability;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_role_pages(): void
    {
        $this->get(route('roles.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_role_index(): void
    {
        $admin = $this->adminUser();

        $this
            ->actingAs($admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Roles/Index')
                ->has('roles.data')
            );
    }

    public function test_authenticated_user_can_view_permission_matrix(): void
    {
        $admin = $this->adminUser();

        $this
            ->actingAs($admin)
            ->get(route('roles.matrix'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Roles/Matrix')
                ->has('roles')
                ->has('matrix')
                ->has('specialCapabilities')
                ->where('actions.view', '查看')
                ->where('actions.create', '新增')
                ->where('actions.update', '編輯')
                ->where('actions.delete', '刪除')
            );
    }

    public function test_user_can_create_custom_role_with_capabilities(): void
    {
        $admin = $this->adminUser();
        $viewProjects = Capability::where('code', 'projects.projects.view.tenant')->firstOrFail();
        $viewDispatches = Capability::where('code', 'field.dispatches.view.tenant')->firstOrFail();

        $this
            ->actingAs($admin)
            ->post(route('roles.store'), [
                'name' => '自訂工務協調',
                'code' => 'custom_site_coordinator',
                'description' => '只能查看工程與派工',
                'capabilities' => [$viewProjects->id, $viewDispatches->id],
            ])
            ->assertRedirect();

        $role = Role::where('code', 'custom_site_coordinator')->firstOrFail();

        $this->assertFalse($role->is_system);
        $this->assertFalse($role->is_protected);
        $this->assertDatabaseHas('capability_role', [
            'role_id' => $role->id,
            'capability_id' => $viewProjects->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $admin->id,
            'action' => 'assign_capabilities',
            'event' => 'role.capabilities_assigned',
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'module' => 'roles',
        ]);
    }

    public function test_user_can_update_custom_role_capabilities(): void
    {
        $admin = $this->adminUser();
        $role = Role::create([
            'name' => '舊角色',
            'code' => 'old_custom_role',
        ]);
        $capability = Capability::where('code', 'finance.financial_records.view.tenant')->firstOrFail();

        $this
            ->actingAs($admin)
            ->patch(route('roles.update', $role), [
                'name' => '會計查詢角色',
                'code' => 'accounting_reader',
                'description' => '只能查看財務資料',
                'capabilities' => [$capability->id],
            ])
            ->assertRedirect(route('roles.show', $role));

        $role->refresh();

        $this->assertSame('會計查詢角色', $role->name);
        $this->assertTrue($role->capabilities()->where('code', 'finance.financial_records.view.tenant')->exists());
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $admin->id,
            'action' => 'assign_capabilities',
            'event' => 'role.capabilities_synced',
            'subject_type' => Role::class,
            'subject_id' => $role->id,
            'module' => 'roles',
        ]);
    }

    public function test_protected_system_role_cannot_be_deleted(): void
    {
        $admin = $this->adminUser();
        $role = Role::where('code', 'admin')->firstOrFail();

        $this
            ->actingAs($admin)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_user_without_capability_cannot_manage_roles(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    public function test_authorizer_reads_capabilities_without_role_name_checks(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $role = Role::where('code', 'accounting')->firstOrFail();
        $user->roles()->attach($role);

        $authorizer = app(CapabilityAuthorizer::class);

        $this->assertTrue($authorizer->allows($user, 'finance.financial_records.view.tenant'));
        $this->assertFalse($authorizer->allows($user, 'security.users.delete.tenant'));
    }

    private function adminUser(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $role = Role::where('code', 'admin')->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }
}
