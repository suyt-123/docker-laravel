<?php

namespace Tests\Feature;

use App\Models\Capability;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\DocumentAttachment;
use App\Models\FinancialRecord;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\ProgressLog;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectSubresourceIdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_change_order_id_cannot_bypass_project_visibility(): void
    {
        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'projects.change_orders.view.tenant',
        ]);
        $order = ProjectChangeOrder::create([
            'project_id' => $this->project('TPH-2026-SUB-CO')->id,
            'title' => '不可見專案追加單',
            'amount' => 10000,
            'status' => 'draft',
        ]);

        $this
            ->actingAs($user)
            ->get(route('project-change-orders.show', $order))
            ->assertForbidden();
    }

    public function test_dispatch_id_cannot_bypass_project_visibility(): void
    {
        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'field.dispatches.view.assigned',
        ]);
        $dispatch = Dispatch::create([
            'project_id' => $this->project('TPH-2026-SUB-DISPATCH')->id,
            'work_item' => '不可見專案派工',
            'status' => 'scheduled',
            'scheduled_date' => '2026-06-10',
        ]);

        $this
            ->actingAs($user)
            ->get(route('dispatches.show', $dispatch))
            ->assertForbidden();
    }

    public function test_dispatch_bound_actions_cannot_bypass_project_visibility(): void
    {
        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'field.dispatches.view.assigned',
            'field.dispatches.update.tenant',
            'field.dispatches.delete.tenant',
        ]);

        $cases = [
            ['method' => 'get', 'route' => 'dispatches.edit'],
            ['method' => 'patch', 'route' => 'dispatches.update', 'payload' => fn (Dispatch $dispatch) => [
                'project_id' => $dispatch->project_id,
                'work_item' => '不應更新派工',
                'status' => 'completed',
                'scheduled_date' => '2026-06-11',
            ]],
            ['method' => 'delete', 'route' => 'dispatches.destroy'],
        ];

        foreach ($cases as $index => $case) {
            $dispatch = Dispatch::create([
                'project_id' => $this->project('TPH-2026-SUB-DISPATCH-ACT-'.$index)->id,
                'work_item' => '不可見專案派工 '.$index,
                'status' => 'scheduled',
                'scheduled_date' => '2026-06-10',
            ]);

            $payload = isset($case['payload']) ? $case['payload']($dispatch) : [];

            $this
                ->actingAs($user)
                ->{$case['method']}(route($case['route'], $dispatch), $payload)
                ->assertForbidden();

            $this->assertDatabaseHas('dispatches', [
                'id' => $dispatch->id,
                'work_item' => '不可見專案派工 '.$index,
                'status' => 'scheduled',
            ]);
        }
    }

    public function test_progress_log_id_cannot_bypass_project_visibility(): void
    {
        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'field.progress_logs.view.assigned',
        ]);
        $log = ProgressLog::create([
            'project_id' => $this->project('TPH-2026-SUB-LOG')->id,
            'work_date' => '2026-06-10',
            'progress_percent' => 20,
        ]);

        $this
            ->actingAs($user)
            ->get(route('progress-logs.show', $log))
            ->assertForbidden();
    }

    public function test_financial_record_id_cannot_bypass_project_visibility(): void
    {
        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'finance.financial_records.view.tenant',
        ]);
        $record = FinancialRecord::create([
            'project_id' => $this->project('TPH-2026-SUB-FIN')->id,
            'type' => 'deposit',
            'title' => '不可見專案訂金',
            'amount' => 10000,
            'status' => 'pending',
        ]);

        $this
            ->actingAs($user)
            ->get(route('financial-records.show', $record))
            ->assertForbidden();
    }

    public function test_inventory_transaction_id_cannot_bypass_project_visibility(): void
    {
        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'inventory.inventory_transactions.view.tenant',
        ]);
        $material = Material::create([
            'name' => 'IDOR 測試材料',
            'unit' => '支',
        ]);
        $transaction = InventoryTransaction::create([
            'material_id' => $material->id,
            'project_id' => $this->project('TPH-2026-SUB-INV')->id,
            'type' => 'inbound',
            'quantity' => 1,
            'unit' => '支',
            'unit_cost' => 100,
            'total_cost' => 100,
            'occurred_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('inventory-transactions.show', $transaction))
            ->assertForbidden();
    }

    public function test_inventory_transaction_bound_actions_cannot_bypass_project_visibility(): void
    {
        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'inventory.inventory_transactions.view.tenant',
            'inventory.inventory_transactions.update.tenant',
            'inventory.inventory_transactions.delete.tenant',
        ]);
        $material = Material::create([
            'name' => 'IDOR 異動測試材料',
            'unit' => '支',
            'current_stock' => 10,
        ]);

        $cases = [
            ['method' => 'get', 'route' => 'inventory-transactions.edit'],
            ['method' => 'patch', 'route' => 'inventory-transactions.update', 'payload' => fn (InventoryTransaction $transaction) => [
                'material_id' => $transaction->material_id,
                'project_id' => $transaction->project_id,
                'type' => 'outbound',
                'quantity' => 2,
                'unit' => '支',
                'unit_cost' => 250,
                'reference_no' => '不應更新',
            ]],
            ['method' => 'delete', 'route' => 'inventory-transactions.destroy'],
        ];

        foreach ($cases as $index => $case) {
            $transaction = InventoryTransaction::create([
                'material_id' => $material->id,
                'project_id' => $this->project('TPH-2026-SUB-INV-ACT-'.$index)->id,
                'type' => 'inbound',
                'quantity' => 1,
                'unit' => '支',
                'unit_cost' => 100,
                'total_cost' => 100,
                'reference_no' => '原始異動 '.$index,
                'occurred_at' => now(),
            ]);

            $payload = isset($case['payload']) ? $case['payload']($transaction) : [];

            $this
                ->actingAs($user)
                ->{$case['method']}(route($case['route'], $transaction), $payload)
                ->assertForbidden();

            $this->assertDatabaseHas('inventory_transactions', [
                'id' => $transaction->id,
                'type' => 'inbound',
                'quantity' => 1,
                'reference_no' => '原始異動 '.$index,
            ]);
            $this->assertDatabaseHas('materials', [
                'id' => $material->id,
                'current_stock' => 10,
            ]);
        }
    }

    public function test_quotation_linked_to_project_cannot_bypass_project_visibility(): void
    {
        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'sales.quotations.view.tenant',
        ]);
        $quotation = $this->quotationLinkedToProject('Q-2026-SUB-QUOTE', $this->project('TPH-2026-SUB-QUOTE'));

        $this
            ->actingAs($user)
            ->get(route('quotations.show', $quotation))
            ->assertForbidden();
    }

    public function test_quotation_attachment_id_cannot_bypass_project_visibility(): void
    {
        Storage::fake('public');

        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'sales.quotations.update.tenant',
        ]);
        $quotation = $this->quotationLinkedToProject('Q-2026-SUB-ATTACH', $this->project('TPH-2026-SUB-ATTACH'));
        $attachment = DocumentAttachment::create([
            'attachable_type' => Quotation::class,
            'attachable_id' => $quotation->id,
            'uploaded_by' => $user->id,
            'file_path' => 'quotation-attachments/test/subresource.pdf',
            'original_name' => 'subresource.pdf',
            'mime_type' => 'application/pdf',
            'size' => 10,
        ]);

        Storage::disk('public')->put($attachment->file_path, 'pdf');

        $this
            ->actingAs($user)
            ->delete(route('quotations.attachments.destroy', $attachment))
            ->assertForbidden();
    }

    public function test_quotation_bound_actions_cannot_bypass_linked_project_visibility(): void
    {
        Storage::fake('public');

        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'sales.quotations.view.tenant',
            'sales.quotations.export_pdf.tenant',
            'sales.quotations.update.tenant',
            'sales.quotations.delete.tenant',
            'sales.quotations.submit_review.tenant',
            'sales.quotations.approve.tenant',
            'sales.quotations.reject.tenant',
            'sales.quotations.send_customer.tenant',
            'sales.quotations.confirm_customer.tenant',
            'sales.quotations.convert_project.tenant',
            'sales.quotations.void.tenant',
            'sales.quotations.reopen.tenant',
        ]);

        $cases = [
            'pdf' => ['status' => 'draft', 'method' => 'get', 'route' => 'quotations.pdf'],
            'edit' => ['status' => 'draft', 'method' => 'get', 'route' => 'quotations.edit'],
            'update' => ['status' => 'draft', 'method' => 'patch', 'route' => 'quotations.update', 'payload' => fn (Quotation $quotation) => $this->quotationPayload($quotation)],
            'destroy' => ['status' => 'draft', 'method' => 'delete', 'route' => 'quotations.destroy'],
            'submit-review' => ['status' => 'draft', 'method' => 'post', 'route' => 'quotations.submit-review'],
            'approve' => ['status' => 'reviewing', 'method' => 'post', 'route' => 'quotations.approve'],
            'reject' => ['status' => 'reviewing', 'method' => 'post', 'route' => 'quotations.reject'],
            'send-customer' => ['status' => 'approved', 'method' => 'post', 'route' => 'quotations.send-customer'],
            'accept-customer' => ['status' => 'approved', 'method' => 'post', 'route' => 'quotations.accept-customer'],
            'decline-customer' => ['status' => 'approved', 'method' => 'post', 'route' => 'quotations.decline-customer'],
            'convert-project' => ['status' => 'accepted', 'method' => 'post', 'route' => 'quotations.convert-project'],
            'void' => ['status' => 'draft', 'method' => 'post', 'route' => 'quotations.void'],
            'reopen' => ['status' => 'approved', 'method' => 'post', 'route' => 'quotations.reopen'],
            'store-attachment' => ['status' => 'draft', 'method' => 'post', 'route' => 'quotations.attachments.store', 'payload' => fn () => [
                'file' => UploadedFile::fake()->create('blocked.pdf', 12, 'application/pdf'),
            ]],
        ];

        foreach ($cases as $action => $case) {
            $quotation = $this->quotationLinkedToProject(
                'Q-2026-SUB-'.str($action)->upper()->replace('-', '')->limit(12, ''),
                $this->project('TPH-2026-SUB-'.str($action)->upper()->replace('-', '')->limit(12, '')),
                ['status' => $case['status']],
            );

            $payload = isset($case['payload']) ? $case['payload']($quotation) : [];

            $this
                ->actingAs($user)
                ->{$case['method']}(route($case['route'], $quotation), $payload)
                ->assertForbidden();

            $this->assertDatabaseHas('quotations', [
                'id' => $quotation->id,
                'status' => $case['status'],
            ]);
        }
    }

    public function test_financial_record_bound_actions_cannot_bypass_project_visibility(): void
    {
        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'finance.financial_records.view.tenant',
            'finance.financial_records.update.tenant',
            'finance.financial_records.delete.tenant',
        ]);

        $cases = [
            ['method' => 'get', 'route' => 'financial-records.edit'],
            ['method' => 'patch', 'route' => 'financial-records.update', 'payload' => fn (FinancialRecord $record) => [
                'project_id' => $record->project_id,
                'type' => 'progress',
                'title' => '不應更新',
                'amount' => 20000,
                'status' => 'paid',
            ]],
            ['method' => 'delete', 'route' => 'financial-records.destroy'],
        ];

        foreach ($cases as $index => $case) {
            $record = FinancialRecord::create([
                'project_id' => $this->project('TPH-2026-SUB-FIN-ACT-'.$index)->id,
                'type' => 'deposit',
                'title' => '不可見專案收款 '.$index,
                'amount' => 10000,
                'status' => 'pending',
            ]);

            $payload = isset($case['payload']) ? $case['payload']($record) : [];

            $this
                ->actingAs($user)
                ->{$case['method']}(route($case['route'], $record), $payload)
                ->assertForbidden();

            $this->assertDatabaseHas('financial_records', [
                'id' => $record->id,
                'title' => '不可見專案收款 '.$index,
                'status' => 'pending',
            ]);
        }
    }

    public function test_project_change_order_bound_actions_cannot_bypass_project_visibility(): void
    {
        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'projects.change_orders.update.tenant',
            'projects.change_orders.delete.tenant',
            'projects.change_orders.submit_review.tenant',
            'projects.change_orders.approve.tenant',
            'projects.change_orders.confirm_customer.tenant',
            'projects.change_orders.cancel.tenant',
            'projects.change_orders.create_quotation.tenant',
            'projects.change_orders.convert_financial_record.tenant',
        ]);

        $cases = [
            'edit' => ['status' => 'draft', 'method' => 'get', 'route' => 'project-change-orders.edit'],
            'update' => ['status' => 'draft', 'method' => 'patch', 'route' => 'project-change-orders.update', 'payload' => fn (ProjectChangeOrder $order) => $this->changeOrderPayload($order)],
            'destroy' => ['status' => 'draft', 'method' => 'delete', 'route' => 'project-change-orders.destroy'],
            'submit-review' => ['status' => 'draft', 'method' => 'post', 'route' => 'project-change-orders.submit-review'],
            'approve' => ['status' => 'pending_approval', 'method' => 'post', 'route' => 'project-change-orders.approve'],
            'confirm-customer' => ['status' => 'approved', 'method' => 'post', 'route' => 'project-change-orders.confirm-customer'],
            'cancel' => ['status' => 'draft', 'method' => 'post', 'route' => 'project-change-orders.cancel'],
            'create-quotation' => ['status' => 'approved', 'method' => 'post', 'route' => 'project-change-orders.create-quotation', 'requires_formal_quotation' => true],
            'convert-financial-record' => ['status' => 'customer_confirmed', 'method' => 'post', 'route' => 'project-change-orders.convert-financial-record'],
        ];

        foreach ($cases as $action => $case) {
            $order = ProjectChangeOrder::create([
                'project_id' => $this->project('TPH-2026-SUB-CO-'.str($action)->upper()->replace('-', '')->limit(10, ''))->id,
                'title' => '不可見專案追加單 '.$action,
                'amount' => 10000,
                'status' => $case['status'],
                'requires_formal_quotation' => $case['requires_formal_quotation'] ?? false,
            ]);

            $payload = isset($case['payload']) ? $case['payload']($order) : [];

            $this
                ->actingAs($user)
                ->{$case['method']}(route($case['route'], $order), $payload)
                ->assertForbidden();

            $this->assertDatabaseHas('project_change_orders', [
                'id' => $order->id,
                'status' => $case['status'],
                'financial_record_id' => null,
                'quotation_id' => null,
            ]);
        }
    }

    public function test_progress_photo_id_cannot_bypass_project_visibility(): void
    {
        Storage::fake('public');

        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'field.progress_logs.delete.tenant',
        ]);
        $project = $this->project('TPH-2026-SUB-PHOTO');
        $log = ProgressLog::create([
            'project_id' => $project->id,
            'work_date' => '2026-06-10',
            'progress_percent' => 20,
        ]);
        $photo = $log->photos()->create([
            'project_id' => $project->id,
            'file_path' => 'progress-photos/test/subresource.png',
        ]);

        Storage::disk('public')->put($photo->file_path, 'png');

        $this
            ->actingAs($user)
            ->delete(route('progress-photos.destroy', $photo))
            ->assertForbidden();
    }

    /**
     * @param  array<int, string>  $capabilityCodes
     */
    private function userWithCapabilities(array $capabilityCodes): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Subresource IDOR Role '.str()->random(8),
            'code' => 'subresource_idor_role_'.str()->random(8),
        ]);

        $role->capabilities()->sync(
            Capability::query()
                ->whereIn('code', $capabilityCodes)
                ->pluck('id'),
        );
        $user->roles()->attach($role);

        return $user;
    }

    private function project(string $projectNo): Project
    {
        $customer = Customer::create(['name' => $projectNo.' 客戶']);

        return Project::create([
            'project_no' => $projectNo,
            'customer_id' => $customer->id,
            'name' => $projectNo.' 工程',
            'status' => 'inquiry',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function quotationLinkedToProject(string $quotationNo, Project $project, array $attributes = []): Quotation
    {
        return Quotation::create([
            'quotation_no' => $quotationNo,
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'status' => 'draft',
            'subtotal' => 1000,
            'total' => 1000,
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function quotationPayload(Quotation $quotation): array
    {
        return [
            'quotation_no' => $quotation->quotation_no,
            'customer_id' => $quotation->customer_id,
            'project_id' => $quotation->project_id,
            'status' => $quotation->status,
            'tax' => 0,
            'discount' => 0,
            'items' => [
                [
                    'name' => '不可更新項目',
                    'unit' => '式',
                    'quantity' => 1,
                    'unit_price' => 1000,
                    'cost_price' => 0,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function changeOrderPayload(ProjectChangeOrder $order): array
    {
        return [
            'project_id' => $order->project_id,
            'title' => '不應更新追加單',
            'amount' => 20000,
            'status' => $order->status,
        ];
    }
}
