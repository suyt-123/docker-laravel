<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkCrew;
use App\Models\Worker;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_only_sees_own_dispatches(): void
    {
        $this->seed(RbacSeeder::class);

        $workerUser = $this->userWithRole('worker');
        $otherUser = User::factory()->create();
        $crew = WorkCrew::create(['name' => '現場工班']);
        $worker = Worker::create([
            'user_id' => $workerUser->id,
            'work_crew_id' => $crew->id,
            'name' => '本人師傅',
        ]);
        $otherWorker = Worker::create([
            'user_id' => $otherUser->id,
            'name' => '其他師傅',
        ]);
        $project = $this->project(['work_crew_id' => $crew->id]);
        $ownDispatch = Dispatch::create([
            'project_id' => $project->id,
            'work_item' => '本人派工',
            'status' => 'scheduled',
            'scheduled_date' => '2026-05-10',
        ]);
        $ownDispatch->workers()->attach($worker);

        $otherDispatch = Dispatch::create([
            'project_id' => $project->id,
            'work_item' => '其他派工',
            'status' => 'scheduled',
            'scheduled_date' => '2026-05-11',
        ]);
        $otherDispatch->workers()->attach($otherWorker);

        $this
            ->actingAs($workerUser)
            ->get(route('dispatches.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dispatches/Index')
                ->has('dispatches.data', 1)
                ->where('dispatches.data.0.work_item', '本人派工')
            );
    }

    public function test_crew_leader_sees_assigned_crew_workers(): void
    {
        $this->seed(RbacSeeder::class);

        $leaderUser = $this->userWithRole('crew_leader');
        $crew = WorkCrew::create(['name' => '北區工班']);
        Worker::create([
            'user_id' => $leaderUser->id,
            'work_crew_id' => $crew->id,
            'name' => '工班負責人',
        ]);
        Worker::create([
            'work_crew_id' => $crew->id,
            'name' => '同工班師傅',
        ]);
        Worker::create(['name' => '外部師傅']);

        $this
            ->actingAs($leaderUser)
            ->get(route('workers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Workers/Index')
                ->has('workers.data', 2)
            );
    }

    public function test_worker_sees_project_when_assigned_through_dispatch(): void
    {
        $this->seed(RbacSeeder::class);

        $workerUser = $this->userWithRole('worker');
        $crew = WorkCrew::create(['name' => '派工工班']);
        $worker = Worker::create([
            'user_id' => $workerUser->id,
            'work_crew_id' => $crew->id,
            'name' => '派工師傅',
        ]);
        $project = $this->project([
            'project_no' => 'TPH-2026-DISPATCH-SCOPE',
            'work_crew_id' => null,
        ]);
        $dispatch = Dispatch::create([
            'project_id' => $project->id,
            'work_crew_id' => $crew->id,
            'work_item' => '透過派工可見',
            'status' => 'scheduled',
            'scheduled_date' => '2026-05-10',
        ]);
        $dispatch->workers()->attach($worker);

        $this
            ->actingAs($workerUser)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Index')
                ->has('projects.data', 1)
                ->where('projects.data.0.project_no', 'TPH-2026-DISPATCH-SCOPE')
            );
    }

    public function test_crew_leader_sees_project_when_crew_is_assigned_through_dispatch(): void
    {
        $this->seed(RbacSeeder::class);

        $leaderUser = $this->userWithRole('crew_leader');
        $crew = WorkCrew::create(['name' => '派工工班']);
        Worker::create([
            'user_id' => $leaderUser->id,
            'work_crew_id' => $crew->id,
            'name' => '工班負責人',
        ]);
        $project = $this->project([
            'project_no' => 'TPH-2026-CREW-SCOPE',
            'work_crew_id' => null,
        ]);
        Dispatch::create([
            'project_id' => $project->id,
            'work_crew_id' => $crew->id,
            'work_item' => '工班派工可見',
            'status' => 'scheduled',
            'scheduled_date' => '2026-05-10',
        ]);

        $this
            ->actingAs($leaderUser)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Index')
                ->has('projects.data', 1)
                ->where('projects.data.0.project_no', 'TPH-2026-CREW-SCOPE')
            );
    }

    public function test_user_without_any_view_scope_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dispatches.index'))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $roleCode, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function project(array $attributes = []): Project
    {
        $customer = Customer::create(['name' => '測試客戶']);

        return Project::create([
            'project_no' => 'TPH-2026-SCOPE',
            'customer_id' => $customer->id,
            'name' => '資料範圍工程',
            ...$attributes,
        ]);
    }
}
