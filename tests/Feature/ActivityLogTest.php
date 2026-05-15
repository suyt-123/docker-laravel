<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_activity_logs(): void
    {
        $this->get(route('activity-logs.index'))->assertRedirect(route('login'));
    }

    public function test_model_changes_are_recorded_in_activity_logs(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '稽核客戶']);

        $this
            ->actingAs($user)
            ->post(route('projects.store'), [
                'customer_id' => $customer->id,
                'project_no' => 'TPH-2026-0099',
                'name' => '稽核測試工程',
                'status' => 'inquiry',
            ])
            ->assertRedirect();

        $project = Project::where('project_no', 'TPH-2026-0099')->firstOrFail();

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'create',
            'event' => 'model.created',
            'subject_type' => Project::class,
            'subject_id' => $project->id,
            'module' => 'projects',
            'subject_label' => '稽核測試工程',
        ]);
    }

    public function test_admin_can_view_activity_log_index_and_detail(): void
    {
        $user = $this->authorizedUser();
        $log = ActivityLog::create([
            'actor_id' => $user->id,
            'action' => 'update',
            'event' => 'model.updated',
            'subject_type' => Project::class,
            'subject_id' => 123,
            'subject_label' => '測試工程',
            'module' => 'projects',
            'old_values' => ['status' => 'inquiry'],
            'new_values' => ['status' => 'quoted'],
        ]);

        $this
            ->actingAs($user)
            ->get(route('activity-logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ActivityLogs/Index')
                ->has('activityLogs.data')
            );

        $this
            ->actingAs($user)
            ->get(route('activity-logs.show', $log))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ActivityLogs/Show')
                ->where('activityLog.subject_label', '測試工程')
                ->where('activityLog.old_values.status', 'inquiry')
                ->where('activityLog.new_values.status', 'quoted')
            );
    }

    public function test_user_without_capability_cannot_view_activity_logs(): void
    {
        $user = $this->authorizedUser(roleCode: 'worker');

        $this
            ->actingAs($user)
            ->get(route('activity-logs.index'))
            ->assertForbidden();
    }
}
