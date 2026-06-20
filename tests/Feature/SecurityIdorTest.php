<?php

namespace Tests\Feature;

use App\Models\Capability;
use App\Models\Customer;
use App\Models\DocumentAttachment;
use App\Models\FinancialRecord;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityIdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_assigned_scope_cannot_directly_view_unassigned_project(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $role = $this->roleWithCapabilities(['projects.projects.view.assigned']);
        $user->roles()->attach($role);
        $project = $this->project('TPH-2026-IDOR1');

        $this
            ->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_project_assigned_scope_can_directly_view_assigned_project(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $role = $this->roleWithCapabilities(['projects.projects.view.assigned']);
        $user->roles()->attach($role);
        $project = $this->project('TPH-2026-IDOR-ASSIGNED', ['manager_id' => $user->id]);

        $this
            ->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk();
    }

    public function test_project_assigned_scope_cannot_directly_edit_unassigned_project(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $role = $this->roleWithCapabilities([
            'projects.projects.view.assigned',
            'projects.projects.update.tenant',
        ]);
        $user->roles()->attach($role);
        $project = $this->project('TPH-2026-IDOR-EDIT');

        $this
            ->actingAs($user)
            ->get(route('projects.edit', $project))
            ->assertForbidden();
    }

    public function test_project_assigned_scope_cannot_directly_update_unassigned_project(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $role = $this->roleWithCapabilities([
            'projects.projects.view.assigned',
            'projects.projects.update.tenant',
        ]);
        $user->roles()->attach($role);
        $project = $this->project('TPH-2026-IDOR-UPDATE');

        $this
            ->actingAs($user)
            ->patch(route('projects.update', $project), [
                'project_no' => $project->project_no,
                'customer_id' => $project->customer_id,
                'name' => '不應被更新',
                'status' => 'in_progress',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'TPH-2026-IDOR-UPDATE 工程',
        ]);
    }

    public function test_project_assigned_scope_cannot_directly_delete_unassigned_project(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $role = $this->roleWithCapabilities([
            'projects.projects.view.assigned',
            'projects.projects.delete.tenant',
        ]);
        $user->roles()->attach($role);
        $project = $this->project('TPH-2026-IDOR-DELETE');

        $this
            ->actingAs($user)
            ->delete(route('projects.destroy', $project))
            ->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_project_assigned_scope_cannot_directly_export_invoice_for_unassigned_project(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $role = $this->roleWithCapabilities([
            'projects.projects.view.assigned',
            'finance.financial_records.export_pdf.tenant',
        ]);
        $user->roles()->attach($role);
        $project = $this->project('TPH-2026-IDOR-INVOICE');

        $this
            ->actingAs($user)
            ->get(route('projects.invoice-pdf', $project))
            ->assertForbidden();
    }

    public function test_user_without_quotation_view_cannot_directly_view_quotation(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $quotation = $this->quotation('Q-2026-IDOR1');

        $this
            ->actingAs($user)
            ->get(route('quotations.show', $quotation))
            ->assertForbidden();
    }

    public function test_user_without_financial_record_view_cannot_directly_view_financial_record(): void
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $record = FinancialRecord::create([
            'project_id' => $this->project('TPH-2026-IDOR2')->id,
            'type' => 'deposit',
            'title' => 'IDOR 訂金',
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $this
            ->actingAs($user)
            ->get(route('financial-records.show', $record))
            ->assertForbidden();
    }

    public function test_user_without_user_view_cannot_directly_view_user(): void
    {
        $this->seed(RbacSeeder::class);

        $viewer = User::factory()->create();
        $target = User::factory()->create();

        $this
            ->actingAs($viewer)
            ->get(route('users.show', $target))
            ->assertForbidden();
    }

    public function test_user_without_role_view_cannot_directly_view_role(): void
    {
        $this->seed(RbacSeeder::class);

        $viewer = User::factory()->create();
        $role = Role::where('code', 'admin')->firstOrFail();

        $this
            ->actingAs($viewer)
            ->get(route('roles.show', $role))
            ->assertForbidden();
    }

    public function test_user_without_quotation_update_cannot_delete_attachment_by_id(): void
    {
        Storage::fake('public');
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $quotation = $this->quotation('Q-2026-IDOR2');
        $attachment = DocumentAttachment::create([
            'attachable_type' => Quotation::class,
            'attachable_id' => $quotation->id,
            'uploaded_by' => $user->id,
            'file_path' => 'quotation-attachments/test/contract.pdf',
            'original_name' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
        ]);

        Storage::disk('public')->put($attachment->file_path, 'pdf');

        $this
            ->actingAs($user)
            ->delete(route('quotations.attachments.destroy', $attachment))
            ->assertForbidden();

        $this->assertDatabaseHas('document_attachments', [
            'id' => $attachment->id,
        ]);
        Storage::disk('public')->assertExists($attachment->file_path);
    }

    /**
     * @param  array<int, string>  $capabilityCodes
     */
    private function roleWithCapabilities(array $capabilityCodes): Role
    {
        $role = Role::create([
            'name' => 'Security Test Role '.str()->random(8),
            'code' => 'security_test_role_'.str()->random(8),
        ]);

        $role->capabilities()->sync(
            Capability::query()
                ->whereIn('code', $capabilityCodes)
                ->pluck('id'),
        );

        return $role;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function project(string $projectNo, array $attributes = []): Project
    {
        $customer = Customer::create(['name' => $projectNo.' 客戶']);

        return Project::create([
            'project_no' => $projectNo,
            'customer_id' => $customer->id,
            'name' => $projectNo.' 工程',
            'status' => 'inquiry',
            ...$attributes,
        ]);
    }

    private function quotation(string $quotationNo): Quotation
    {
        $customer = Customer::create(['name' => $quotationNo.' 客戶']);

        return Quotation::create([
            'quotation_no' => $quotationNo,
            'customer_id' => $customer->id,
            'status' => 'draft',
            'subtotal' => 1000,
            'total' => 1000,
        ]);
    }
}
