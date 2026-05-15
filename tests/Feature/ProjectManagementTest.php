<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\ProgressLog;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkCrew;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_project_pages(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_project_index(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '台北鐵皮工程']);

        Project::create([
            'project_no' => 'TPH-2026-0001',
            'customer_id' => $customer->id,
            'name' => '五股廠房屋頂翻修',
            'status' => 'quoted',
        ]);

        $this
            ->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Index')
                ->has('projects.data', 1)
            );
    }

    public function test_authenticated_user_can_create_project(): void
    {
        $user = $this->authorizedUser();
        $manager = User::factory()->create(['name' => '王主任']);
        $customer = Customer::create(['name' => '新北鋼構客戶']);
        $crew = WorkCrew::create(['name' => '北區鋼構班']);

        $this
            ->actingAs($user)
            ->post(route('projects.store'), [
                'project_no' => 'TPH-2026-0101',
                'customer_id' => $customer->id,
                'manager_id' => $manager->id,
                'work_crew_id' => $crew->id,
                'name' => '新莊鐵皮屋新建',
                'type' => '鐵皮屋工程',
                'status' => 'contracted',
                'address' => '新北市新莊區測試路 1 號',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-15',
                'contract_amount' => 500000,
                'estimated_cost' => 320000,
                'actual_cost' => 300000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'project_no' => 'TPH-2026-0101',
            'customer_id' => $customer->id,
            'name' => '新莊鐵皮屋新建',
            'gross_profit' => 200000,
        ]);
    }

    public function test_project_number_is_generated_when_blank(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '自動編號客戶']);

        $this
            ->actingAs($user)
            ->post(route('projects.store'), [
                'project_no' => '',
                'customer_id' => $customer->id,
                'name' => '自動編號案件',
                'status' => 'inquiry',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => '自動編號案件',
            'project_no' => 'TPH-'.now()->format('Y').'-0001',
        ]);
    }

    public function test_project_requires_customer_and_name(): void
    {
        $user = $this->authorizedUser();

        $this
            ->actingAs($user)
            ->from(route('projects.create'))
            ->post(route('projects.store'), [
                'name' => '',
                'status' => 'inquiry',
            ])
            ->assertRedirect(route('projects.create'))
            ->assertSessionHasErrors(['customer_id', 'name']);
    }

    public function test_authenticated_user_can_update_project(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '原客戶']);
        $project = Project::create([
            'project_no' => 'TPH-2026-0002',
            'customer_id' => $customer->id,
            'name' => '舊案件',
            'status' => 'inquiry',
        ]);

        $this
            ->actingAs($user)
            ->patch(route('projects.update', $project), [
                'project_no' => 'TPH-2026-0002',
                'customer_id' => $customer->id,
                'name' => '更新後案件',
                'status' => 'in_progress',
                'contract_amount' => 250000,
                'estimated_cost' => 180000,
                'actual_cost' => 150000,
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => '更新後案件',
            'status' => 'in_progress',
            'gross_profit' => 100000,
        ]);
    }

    public function test_authenticated_user_can_delete_project(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '可刪案件客戶']);
        $project = Project::create([
            'project_no' => 'TPH-2026-0003',
            'customer_id' => $customer->id,
            'name' => '可刪案件',
            'status' => 'inquiry',
        ]);

        $this
            ->actingAs($user)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseMissing('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_deleting_project_removes_related_photo_files(): void
    {
        Storage::fake('public');

        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '可刪照片案件客戶']);
        $project = Project::create([
            'project_no' => 'TPH-2026-0004',
            'customer_id' => $customer->id,
            'name' => '可刪照片案件',
            'status' => 'in_progress',
        ]);
        $log = ProgressLog::create([
            'project_id' => $project->id,
            'work_date' => '2026-06-01',
            'progress_percent' => 30,
        ]);
        $path = 'progress-photos/test/project-delete.png';

        Storage::disk('public')->put($path, 'photo-content');
        $log->photos()->create([
            'project_id' => $project->id,
            'file_path' => $path,
        ]);
        $dispatch = Dispatch::create([
            'project_id' => $project->id,
            'work_item' => '屋頂施工',
            'status' => 'in_progress',
            'scheduled_date' => '2026-06-01',
        ]);
        $attendancePath = 'attendance-photos/test/project-delete.png';

        Storage::disk('public')->put($attendancePath, 'attendance-photo-content');
        AttendanceRecord::create([
            'dispatch_id' => $dispatch->id,
            'project_id' => $project->id,
            'type' => 'clock_in',
            'recorded_at' => '2026-06-01 08:00:00',
            'photo_path' => $attendancePath,
        ]);

        $this
            ->actingAs($user)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('progress_photos', ['file_path' => $path]);
        $this->assertDatabaseMissing('attendance_records', ['photo_path' => $attendancePath]);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertMissing($attendancePath);
    }
}
