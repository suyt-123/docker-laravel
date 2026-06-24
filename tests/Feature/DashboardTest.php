<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\FinancialRecord;
use App\Models\Material;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkCrew;
use App\Models\Worker;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_operational_metrics(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '儀表板客戶']);
        $project = Project::create([
            'project_no' => 'TPH-2026-0001',
            'customer_id' => $customer->id,
            'name' => '儀表板案件',
            'status' => 'in_progress',
        ]);
        $crew = WorkCrew::create(['name' => '儀表板工班']);

        Dispatch::create([
            'project_id' => $project->id,
            'work_crew_id' => $crew->id,
            'work_item' => '今日施工',
            'status' => 'scheduled',
            'scheduled_date' => now()->toDateString(),
        ]);

        FinancialRecord::create([
            'project_id' => $project->id,
            'type' => 'deposit',
            'title' => '逾期訂金',
            'amount' => 100000,
            'due_date' => now()->subDay()->toDateString(),
            'status' => 'overdue',
        ]);

        Material::create([
            'name' => '低庫存 C 型鋼',
            'unit' => '支',
            'safe_stock' => 10,
            'current_stock' => 3,
        ]);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('widgets.dispatches', true)
                ->where('widgets.projects', true)
                ->where('widgets.financialRecords', true)
                ->where('widgets.materials', true)
                ->where('metrics.today_dispatches', 1)
                ->where('metrics.active_projects', 1)
                ->where('metrics.unpaid_amount', 100000)
                ->where('metrics.overdue_amount', 100000)
                ->where('metrics.low_stock_count', 1)
                ->has('todayDispatches', 1)
                ->has('overdueRecords', 1)
                ->has('lowStockMaterials', 1)
                ->has('projectStatusCounts', 1)
            );
    }

    public function test_worker_dashboard_only_shows_allowed_widgets_and_scoped_data(): void
    {
        $this->seed(RbacSeeder::class);

        $workerUser = User::factory()->create(['name' => 'Worker']);
        $role = Role::where('code', 'worker')->firstOrFail();
        $workerUser->roles()->attach($role);

        $customer = Customer::create(['name' => '師傅儀表板客戶']);
        $crew = WorkCrew::create(['name' => '師傅工班']);
        $worker = Worker::create([
            'user_id' => $workerUser->id,
            'work_crew_id' => $crew->id,
            'name' => '登入師傅',
        ]);
        $project = Project::create([
            'project_no' => 'TPH-2026-WORKER',
            'customer_id' => $customer->id,
            'work_crew_id' => $crew->id,
            'name' => '師傅可見案件',
            'status' => 'in_progress',
        ]);
        $ownDispatch = Dispatch::create([
            'project_id' => $project->id,
            'work_crew_id' => $crew->id,
            'work_item' => '本人今日施工',
            'status' => 'scheduled',
            'scheduled_date' => now()->toDateString(),
        ]);
        $ownDispatch->workers()->attach($worker);

        FinancialRecord::create([
            'project_id' => $project->id,
            'type' => 'deposit',
            'title' => '不應顯示的收款',
            'amount' => 100000,
            'due_date' => now()->subDay()->toDateString(),
            'status' => 'overdue',
        ]);

        Material::create([
            'name' => '不應顯示的低庫存',
            'unit' => '支',
            'safe_stock' => 10,
            'current_stock' => 3,
        ]);

        $this
            ->actingAs($workerUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('widgets.dispatches', true)
                ->where('widgets.projects', true)
                ->where('widgets.financialRecords', false)
                ->where('widgets.materials', false)
                ->where('metrics.today_dispatches', 1)
                ->where('metrics.active_projects', 1)
                ->where('metrics.unpaid_amount', null)
                ->where('metrics.overdue_amount', null)
                ->where('metrics.low_stock_count', null)
                ->has('todayDispatches', 1)
                ->has('unpaidRecords', 0)
                ->has('overdueRecords', 0)
                ->has('lowStockMaterials', 0)
                ->has('projectStatusCounts', 1)
            );
    }
}
