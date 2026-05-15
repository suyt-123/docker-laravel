<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Worker;
use App\Models\WorkCrew;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_worker_pages(): void
    {
        $this->get(route('workers.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_create_worker(): void
    {
        $user = $this->authorizedUser();
        $workerAccount = User::factory()->create([
            'name' => '林師傅帳號',
            'email' => 'lin-worker@example.com',
        ]);
        $crew = WorkCrew::create(['name' => '北區鋼構班']);

        $this->actingAs($user)->post(route('workers.store'), [
            'user_id' => $workerAccount->id,
            'work_crew_id' => $crew->id,
            'name' => '林師傅',
            'phone' => '0912-111-222',
            'role' => '焊接',
            'daily_rate' => 3000,
            'certifications_text' => "高空作業\n焊接證照",
            'insurance_expires_at' => '2026-12-31',
            'is_active' => true,
        ])->assertRedirect();

        $worker = Worker::where('name', '林師傅')->firstOrFail();
        $this->assertTrue($worker->user->is($workerAccount));
        $this->assertTrue($workerAccount->fresh()->worker->is($worker));
        $this->assertSame(['高空作業', '焊接證照'], $worker->certifications);
        $this->assertTrue($worker->is_active);
    }

    public function test_worker_requires_name(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->from(route('workers.create'))
            ->post(route('workers.store'), ['name' => ''])
            ->assertRedirect(route('workers.create'))
            ->assertSessionHasErrors('name');
    }

    public function test_user_can_update_and_delete_worker(): void
    {
        $user = $this->authorizedUser();
        $workerAccount = $this->authorizedUser();
        $crew = WorkCrew::create(['name' => '南區施工班']);
        $worker = Worker::create(['name' => '舊師傅', 'is_active' => true]);

        $this->actingAs($user)->patch(route('workers.update', $worker), [
            'user_id' => $workerAccount->id,
            'work_crew_id' => $crew->id,
            'name' => '更新師傅',
            'role' => '鎖板',
            'is_active' => false,
            'certifications_text' => '職安訓練',
        ])->assertRedirect(route('workers.show', $worker));

        $worker->refresh();
        $this->assertSame('更新師傅', $worker->name);
        $this->assertSame($workerAccount->id, $worker->user_id);
        $this->assertFalse($worker->is_active);
        $this->assertSame(['職安訓練'], $worker->certifications);

        $this->actingAs($user)
            ->delete(route('workers.destroy', $worker))
            ->assertRedirect(route('workers.index'));

        $this->assertDatabaseMissing('workers', ['id' => $worker->id]);
    }

    public function test_worker_account_binding_must_be_unique(): void
    {
        $user = $this->authorizedUser();
        $workerAccount = $this->authorizedUser();
        Worker::create([
            'user_id' => $workerAccount->id,
            'name' => '已綁定師傅',
        ]);

        $this->actingAs($user)
            ->from(route('workers.create'))
            ->post(route('workers.store'), [
                'user_id' => $workerAccount->id,
                'name' => '重複綁定師傅',
            ])
            ->assertRedirect(route('workers.create'))
            ->assertSessionHasErrors('user_id');
    }
}
