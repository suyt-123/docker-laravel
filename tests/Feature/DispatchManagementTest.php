<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkCrew;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_dispatch_pages(): void
    {
        $this->get(route('dispatches.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_dispatch_index(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();

        Dispatch::create([
            'project_id' => $project->id,
            'work_item' => '屋頂骨架施工',
            'status' => 'scheduled',
            'scheduled_date' => '2026-06-01',
        ]);

        $this
            ->actingAs($user)
            ->get(route('dispatches.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dispatches/Index')
                ->has('dispatches.data', 1)
            );
    }

    public function test_authenticated_user_can_create_dispatch_with_workers(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();
        $crew = WorkCrew::create(['name' => '北區鋼構班']);
        $worker = Worker::create([
            'work_crew_id' => $crew->id,
            'name' => '林師傅',
            'role' => '焊接',
            'daily_rate' => 2800,
        ]);

        $this
            ->actingAs($user)
            ->post(route('dispatches.store'), [
                'project_id' => $project->id,
                'work_crew_id' => $crew->id,
                'work_item' => '屋頂骨架施工',
                'status' => 'scheduled',
                'scheduled_date' => '2026-06-01',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'address' => '新北市五股區測試路 1 號',
                'instructions' => '注意高空作業安全',
                'workers' => [
                    [
                        'id' => $worker->id,
                        'hours' => 8,
                        'wage' => 2800,
                        'note' => '帶焊機',
                    ],
                ],
            ])
            ->assertRedirect();

        $dispatch = Dispatch::where('work_item', '屋頂骨架施工')->firstOrFail();

        $this->assertSame($user->id, $dispatch->created_by);
        $this->assertDatabaseHas('dispatch_worker', [
            'dispatch_id' => $dispatch->id,
            'worker_id' => $worker->id,
            'hours' => 8,
            'wage' => 2800,
        ]);
    }

    public function test_same_work_crew_cannot_be_dispatched_twice_on_same_day(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();
        $crew = WorkCrew::create(['name' => '不可重複工班']);

        Dispatch::create([
            'project_id' => $project->id,
            'work_crew_id' => $crew->id,
            'work_item' => '上午施工',
            'status' => 'scheduled',
            'scheduled_date' => '2026-06-01',
        ]);

        $this
            ->actingAs($user)
            ->post(route('dispatches.store'), [
                'project_id' => $project->id,
                'work_crew_id' => $crew->id,
                'work_item' => '下午施工',
                'status' => 'scheduled',
                'scheduled_date' => '2026-06-01',
            ])
            ->assertStatus(422);
    }

    public function test_same_worker_cannot_be_dispatched_to_different_sites_on_same_day(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();
        $otherProject = $this->project('TPH-2026-0002');
        $worker = Worker::create(['name' => '衝突師傅']);
        $existing = Dispatch::create([
            'project_id' => $project->id,
            'work_item' => '第一場施工',
            'status' => 'scheduled',
            'scheduled_date' => '2026-06-01',
        ]);
        $existing->workers()->attach($worker->id);

        $this
            ->actingAs($user)
            ->post(route('dispatches.store'), [
                'project_id' => $otherProject->id,
                'work_item' => '第二場施工',
                'status' => 'scheduled',
                'scheduled_date' => '2026-06-01',
                'workers' => [
                    ['id' => $worker->id],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_dispatch_requires_project_work_item_status_and_date(): void
    {
        $user = $this->authorizedUser();

        $this
            ->actingAs($user)
            ->from(route('dispatches.create'))
            ->post(route('dispatches.store'), [
                'project_id' => '',
                'work_item' => '',
                'status' => '',
                'scheduled_date' => '',
            ])
            ->assertRedirect(route('dispatches.create'))
            ->assertSessionHasErrors(['project_id', 'work_item', 'status', 'scheduled_date']);
    }

    public function test_authenticated_user_can_update_dispatch_and_replace_workers(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();
        $crew = WorkCrew::create(['name' => '南區施工班']);
        $firstWorker = Worker::create(['work_crew_id' => $crew->id, 'name' => '王師傅']);
        $secondWorker = Worker::create(['work_crew_id' => $crew->id, 'name' => '張師傅']);
        $dispatch = Dispatch::create([
            'project_id' => $project->id,
            'work_crew_id' => $crew->id,
            'work_item' => '舊工項',
            'status' => 'scheduled',
            'scheduled_date' => '2026-06-01',
        ]);
        $dispatch->workers()->attach($firstWorker->id, ['hours' => 4, 'wage' => 1000]);

        $this
            ->actingAs($user)
            ->patch(route('dispatches.update', $dispatch), [
                'project_id' => $project->id,
                'work_crew_id' => $crew->id,
                'work_item' => '浪板安裝',
                'status' => 'completed',
                'scheduled_date' => '2026-06-02',
                'start_time' => '09:00',
                'end_time' => '16:00',
                'workers' => [
                    [
                        'id' => $secondWorker->id,
                        'hours' => 7,
                        'wage' => 2400,
                    ],
                ],
            ])
            ->assertRedirect(route('dispatches.show', $dispatch));

        $dispatch->refresh();

        $this->assertSame('浪板安裝', $dispatch->work_item);
        $this->assertSame('completed', $dispatch->status);
        $this->assertDatabaseMissing('dispatch_worker', [
            'dispatch_id' => $dispatch->id,
            'worker_id' => $firstWorker->id,
        ]);
        $this->assertDatabaseHas('dispatch_worker', [
            'dispatch_id' => $dispatch->id,
            'worker_id' => $secondWorker->id,
            'hours' => 7,
            'wage' => 2400,
        ]);
    }

    public function test_update_dispatch_checks_worker_conflict_but_ignores_itself(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();
        $worker = Worker::create(['name' => '更新衝突師傅']);
        $first = Dispatch::create([
            'project_id' => $project->id,
            'work_item' => '第一張派工',
            'status' => 'scheduled',
            'scheduled_date' => '2026-06-01',
        ]);
        $first->workers()->attach($worker->id);
        $second = Dispatch::create([
            'project_id' => $project->id,
            'work_item' => '第二張派工',
            'status' => 'scheduled',
            'scheduled_date' => '2026-06-02',
        ]);

        $this
            ->actingAs($user)
            ->patch(route('dispatches.update', $first), [
                'project_id' => $project->id,
                'work_item' => '第一張派工更新',
                'status' => 'scheduled',
                'scheduled_date' => '2026-06-01',
                'workers' => [
                    ['id' => $worker->id],
                ],
            ])
            ->assertRedirect(route('dispatches.show', $first));

        $this
            ->actingAs($user)
            ->patch(route('dispatches.update', $second), [
                'project_id' => $project->id,
                'work_item' => '第二張派工撞期',
                'status' => 'scheduled',
                'scheduled_date' => '2026-06-01',
                'workers' => [
                    ['id' => $worker->id],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_user_can_view_simple_dispatch_schedule(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();

        Dispatch::create([
            'project_id' => $project->id,
            'work_item' => '甘特圖施工',
            'status' => 'scheduled',
            'scheduled_date' => '2026-06-01',
        ]);

        $this
            ->actingAs($user)
            ->get(route('dispatches.schedule', [
                'start' => '2026-06-01',
                'days' => 7,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dispatches/Schedule')
                ->has('days', 7)
                ->has('dispatches', 1)
            );
    }

    public function test_authenticated_user_can_delete_dispatch(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();
        $dispatch = Dispatch::create([
            'project_id' => $project->id,
            'work_item' => '可刪派工',
            'status' => 'scheduled',
            'scheduled_date' => '2026-06-01',
        ]);

        $this
            ->actingAs($user)
            ->delete(route('dispatches.destroy', $dispatch))
            ->assertRedirect(route('dispatches.index'));

        $this->assertDatabaseMissing('dispatches', [
            'id' => $dispatch->id,
        ]);
    }

    private function project(string $projectNo = 'TPH-2026-0001'): Project
    {
        $customer = Customer::create(['name' => '派工客戶 '.$projectNo]);

        return Project::create([
            'project_no' => $projectNo,
            'customer_id' => $customer->id,
            'name' => '五股鐵皮屋',
            'status' => 'scheduled',
        ]);
    }
}
