<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Project;
use App\Models\WorkCrew;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_equipment_pages(): void
    {
        $this->get(route('equipment.index'))->assertRedirect(route('login'));
        $this->get(route('equipment-categories.index'))->assertRedirect(route('login'));
        $this->get(route('equipment-transactions.index'))->assertRedirect(route('login'));
    }

    public function test_warehouse_user_can_manage_equipment_categories(): void
    {
        $user = $this->authorizedUser(roleCode: 'warehouse');

        $this->actingAs($user)->post(route('equipment-categories.store'), [
            'name' => '吊掛設備',
            'code' => 'LIFT',
            'description' => '吊車、吊具與相關設備',
            'sort_order' => 10,
            'is_active' => true,
        ])->assertRedirect();

        $category = EquipmentCategory::where('code', 'LIFT')->firstOrFail();

        $this->actingAs($user)->patch(route('equipment-categories.update', $category), [
            'name' => '吊掛與高空設備',
            'code' => 'LIFT',
            'description' => '吊車、吊具與高空作業設備',
            'sort_order' => 20,
            'is_active' => false,
        ])->assertRedirect(route('equipment-categories.show', $category));

        $this->assertDatabaseHas('equipment_categories', [
            'id' => $category->id,
            'name' => '吊掛與高空設備',
            'sort_order' => 20,
            'is_active' => false,
        ]);
    }

    public function test_warehouse_user_can_create_equipment_with_generated_number(): void
    {
        $user = $this->authorizedUser(roleCode: 'warehouse');
        $category = EquipmentCategory::create([
            'name' => '電動工具',
            'code' => 'POWER',
        ]);

        $this->actingAs($user)->post(route('equipment.store'), [
            'equipment_no' => '',
            'equipment_category_id' => $category->id,
            'name' => '電鑽',
            'brand' => 'Makita',
            'model' => 'HP001',
            'status' => 'available',
            'condition' => 'good',
            'purchase_price' => 6800,
        ])->assertRedirect();

        $this->assertDatabaseHas('equipment', [
            'equipment_no' => 'EQ-'.now()->format('Y').'-0001',
            'equipment_category_id' => $category->id,
            'name' => '電鑽',
            'status' => 'available',
            'condition' => 'good',
            'purchase_price' => 6800,
        ]);
    }

    public function test_equipment_check_out_and_check_in_update_current_state(): void
    {
        $user = $this->authorizedUser(roleCode: 'warehouse');
        $crew = WorkCrew::create(['name' => '北區鋼構班']);
        $worker = Worker::create([
            'work_crew_id' => $crew->id,
            'name' => '陳師傅',
            'is_active' => true,
        ]);
        $project = $this->project($crew);
        $equipment = Equipment::create([
            'equipment_no' => 'EQ-2026-0002',
            'name' => '切割機',
            'status' => 'available',
            'condition' => 'good',
        ]);

        $this->actingAs($user)->post(route('equipment.transactions.store', $equipment), [
            'type' => 'check_out',
            'project_id' => $project->id,
            'worker_id' => $worker->id,
            'work_crew_id' => $crew->id,
            'occurred_at' => '2026-05-13 08:00:00',
            'due_at' => '2026-05-13 17:00:00',
            'condition_after' => 'fair',
            'note' => '現場使用',
        ])->assertRedirect(route('equipment.show', $equipment));

        $equipment->refresh();

        $this->assertSame('borrowed', $equipment->status);
        $this->assertSame('fair', $equipment->condition);
        $this->assertSame($project->id, $equipment->current_project_id);
        $this->assertSame($worker->id, $equipment->current_worker_id);
        $this->assertSame($crew->id, $equipment->current_work_crew_id);
        $this->assertDatabaseHas('equipment_transactions', [
            'equipment_id' => $equipment->id,
            'type' => 'check_out',
            'handled_by' => $user->id,
            'condition_before' => 'good',
            'condition_after' => 'fair',
        ]);

        $this->actingAs($user)->post(route('equipment.transactions.store', $equipment), [
            'type' => 'check_in',
            'occurred_at' => '2026-05-13 17:10:00',
            'condition_after' => 'fair',
        ])->assertRedirect(route('equipment.show', $equipment));

        $equipment->refresh();

        $this->assertSame('available', $equipment->status);
        $this->assertNull($equipment->current_project_id);
        $this->assertNull($equipment->current_worker_id);
        $this->assertNull($equipment->current_work_crew_id);
    }

    public function test_equipment_can_be_assigned_to_project(): void
    {
        $user = $this->authorizedUser(roleCode: 'warehouse');
        $crew = WorkCrew::create(['name' => '南區工程班']);
        $project = $this->project($crew, 'TPH-2026-0301');
        $equipment = Equipment::create([
            'equipment_no' => 'EQ-2026-0003',
            'name' => '發電機',
            'status' => 'available',
            'condition' => 'good',
        ]);

        $this->actingAs($user)->post(route('equipment.transactions.store', $equipment), [
            'type' => 'assign_project',
            'project_id' => $project->id,
            'work_crew_id' => $crew->id,
            'occurred_at' => '2026-05-13 09:00:00',
        ])->assertRedirect(route('equipment.show', $equipment));

        $equipment->refresh();

        $this->assertSame('assigned', $equipment->status);
        $this->assertSame($project->id, $equipment->current_project_id);
        $this->assertSame($crew->id, $equipment->current_work_crew_id);
        $this->assertNull($equipment->current_worker_id);
    }

    public function test_category_with_equipment_cannot_be_deleted(): void
    {
        $user = $this->authorizedUser(roleCode: 'warehouse');
        $category = EquipmentCategory::create([
            'name' => '測試分類',
            'code' => 'TEST',
        ]);
        Equipment::create([
            'equipment_no' => 'EQ-2026-0004',
            'equipment_category_id' => $category->id,
            'name' => '測試機具',
            'status' => 'available',
            'condition' => 'good',
        ]);

        $this->actingAs($user)
            ->delete(route('equipment-categories.destroy', $category))
            ->assertStatus(422);

        $this->assertDatabaseHas('equipment_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_worker_without_equipment_management_capability_cannot_create_equipment(): void
    {
        $user = $this->authorizedUser(roleCode: 'worker');

        $this->actingAs($user)->post(route('equipment.store'), [
            'equipment_no' => 'EQ-2026-9999',
            'name' => '不該新增',
            'status' => 'available',
            'condition' => 'good',
        ])->assertForbidden();
    }

    private function project(?WorkCrew $crew = null, string $projectNo = 'TPH-2026-0201'): Project
    {
        $customer = Customer::create(['name' => '測試客戶 '.$projectNo]);

        return Project::create([
            'project_no' => $projectNo,
            'customer_id' => $customer->id,
            'work_crew_id' => $crew?->id,
            'name' => '測試工程 '.$projectNo,
            'status' => 'contracted',
        ]);
    }
}
