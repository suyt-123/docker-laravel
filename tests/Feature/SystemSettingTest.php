<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\WorkCrew;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_system_settings_page(): void
    {
        $user = $this->authorizedUser();

        $this
            ->actingAs($user)
            ->get(route('system-settings.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SystemSettings/Edit')
                ->has('groups.GPS 打卡')
                ->has('groups.公司資料')
            );
    }

    public function test_user_without_setting_capability_is_forbidden(): void
    {
        $user = $this->authorizedUser(roleCode: 'worker');

        $this
            ->actingAs($user)
            ->get(route('system-settings.edit'))
            ->assertForbidden();
    }

    public function test_admin_can_update_system_settings(): void
    {
        $user = $this->authorizedUser();

        $this
            ->actingAs($user)
            ->patch(route('system-settings.update'), [
                'settings' => [
                    'attendance' => [
                        'require_photo' => true,
                        'allowed_distance_meters' => 500,
                        'allow_manual_correction' => true,
                    ],
                    'company' => [
                        'name' => '鼎盛鐵皮工程',
                        'phone' => '02-1234-5678',
                        'address' => '台北市信義區',
                        'tax_id' => '12345678',
                    ],
                    'quotation' => [
                        'default_terms' => '本報價有效期限為 14 天。',
                    ],
                    'inventory' => [
                        'default_safe_stock' => 12,
                    ],
                ],
            ])
            ->assertRedirect(route('system-settings.edit'));

        $this->assertDatabaseHas('system_settings', [
            'key' => 'attendance.require_photo',
            'type' => 'boolean',
            'updated_by' => $user->id,
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key' => 'company.name',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'update',
            'event' => 'system_settings.updated',
            'module' => 'system_settings',
        ]);
    }

    public function test_attendance_distance_uses_system_setting(): void
    {
        $user = $this->authorizedUser();
        [$dispatch, $worker] = $this->siteContext();

        SystemSetting::create([
            'key' => 'attendance.allowed_distance_meters',
            'type' => 'integer',
            'value' => ['value' => 10],
        ]);

        $this
            ->actingAs($user)
            ->post(route('attendance-records.store'), [
                'dispatch_id' => $dispatch->id,
                'worker_id' => $worker->id,
                'type' => 'clock_in',
                'latitude' => 25.034000,
                'longitude' => 121.565000,
            ])
            ->assertRedirect();

        $record = AttendanceRecord::query()->latest('id')->firstOrFail();

        $this->assertFalse($record->is_within_range);
        $this->assertTrue($record->requires_attention);
        $this->assertStringContainsString('10 公尺', $record->anomaly_reason);
    }

    private function siteContext(): array
    {
        $customer = Customer::create(['name' => '設定測試客戶']);
        $crew = WorkCrew::create(['name' => '設定測試工班']);
        $project = Project::create([
            'project_no' => 'TPH-2026-SET1',
            'customer_id' => $customer->id,
            'work_crew_id' => $crew->id,
            'name' => '設定測試工程',
            'status' => 'in_progress',
            'latitude' => 25.0330000,
            'longitude' => 121.5650000,
        ]);
        $dispatch = Dispatch::create([
            'project_id' => $project->id,
            'work_crew_id' => $crew->id,
            'work_item' => '設定測試施工',
            'status' => 'in_progress',
            'scheduled_date' => now()->toDateString(),
        ]);
        $worker = Worker::create([
            'work_crew_id' => $crew->id,
            'name' => '設定測試師傅',
        ]);
        $dispatch->workers()->attach($worker->id);

        return [$dispatch, $worker];
    }
}
