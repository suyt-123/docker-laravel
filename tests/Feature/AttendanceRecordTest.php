<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkCrew;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_attendance_records(): void
    {
        $this->get(route('attendance-records.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_create_clock_in_with_distance_check(): void
    {
        $user = $this->authorizedUser();
        [$project, $dispatch, $worker] = $this->siteContext();

        $this
            ->actingAs($user)
            ->post(route('attendance-records.store'), [
                'dispatch_id' => $dispatch->id,
                'worker_id' => $worker->id,
                'type' => 'clock_in',
                'latitude' => 25.033001,
                'longitude' => 121.565001,
            ])
            ->assertRedirect();

        $record = AttendanceRecord::where('dispatch_id', $dispatch->id)->firstOrFail();

        $this->assertSame($project->id, $record->project_id);
        $this->assertSame($worker->id, $record->worker_id);
        $this->assertSame($user->id, $record->user_id);
        $this->assertSame('clock_in', $record->type);
        $this->assertTrue($record->is_within_range);
        $this->assertFalse($record->requires_attention);
    }

    public function test_duplicate_clock_in_is_marked_for_attention(): void
    {
        $user = $this->authorizedUser();
        [, $dispatch, $worker] = $this->siteContext();

        AttendanceRecord::create([
            'dispatch_id' => $dispatch->id,
            'project_id' => $dispatch->project_id,
            'worker_id' => $worker->id,
            'user_id' => $user->id,
            'type' => 'clock_in',
            'recorded_at' => now(),
            'latitude' => 25.033,
            'longitude' => 121.565,
        ]);

        $this
            ->actingAs($user)
            ->post(route('attendance-records.store'), [
                'dispatch_id' => $dispatch->id,
                'worker_id' => $worker->id,
                'type' => 'clock_in',
                'latitude' => 25.033,
                'longitude' => 121.565,
            ])
            ->assertRedirect();

        $record = AttendanceRecord::query()->orderByDesc('id')->firstOrFail();

        $this->assertTrue($record->is_duplicate);
        $this->assertTrue($record->requires_attention);
        $this->assertStringContainsString('重複', $record->anomaly_reason);
    }

    public function test_clock_out_records_worked_minutes(): void
    {
        $user = $this->authorizedUser();
        [, $dispatch, $worker] = $this->siteContext();

        AttendanceRecord::create([
            'dispatch_id' => $dispatch->id,
            'project_id' => $dispatch->project_id,
            'worker_id' => $worker->id,
            'user_id' => $user->id,
            'type' => 'clock_in',
            'recorded_at' => '2026-05-11 08:00:00',
        ]);

        $this
            ->actingAs($user)
            ->post(route('attendance-records.store'), [
                'dispatch_id' => $dispatch->id,
                'worker_id' => $worker->id,
                'type' => 'clock_out',
                'recorded_at' => '2026-05-11 17:30:00',
            ])
            ->assertRedirect();

        $record = AttendanceRecord::query()->orderByDesc('id')->firstOrFail();

        $this->assertSame(570, $record->worked_minutes);
    }

    public function test_photo_can_be_required_by_system_setting(): void
    {
        SystemSetting::create([
            'key' => 'attendance.require_photo',
            'type' => 'boolean',
            'value' => ['value' => true],
        ]);
        Storage::fake('public');

        $user = $this->authorizedUser();
        [, $dispatch, $worker] = $this->siteContext();

        $this
            ->actingAs($user)
            ->from(route('dispatches.show', $dispatch))
            ->post(route('attendance-records.store'), [
                'dispatch_id' => $dispatch->id,
                'worker_id' => $worker->id,
                'type' => 'clock_in',
            ])
            ->assertRedirect(route('dispatches.show', $dispatch))
            ->assertSessionHasErrors('photo');

        $this
            ->actingAs($user)
            ->post(route('attendance-records.store'), [
                'dispatch_id' => $dispatch->id,
                'worker_id' => $worker->id,
                'type' => 'clock_in',
                'photo' => $this->fakePng('clock-in.png'),
            ])
            ->assertRedirect();

        $record = AttendanceRecord::query()->orderByDesc('id')->firstOrFail();

        $this->assertNotNull($record->photo_path);
        Storage::disk('public')->assertExists($record->photo_path);
    }

    public function test_user_can_view_attendance_records(): void
    {
        $user = $this->authorizedUser();
        [, $dispatch, $worker] = $this->siteContext();

        AttendanceRecord::create([
            'dispatch_id' => $dispatch->id,
            'project_id' => $dispatch->project_id,
            'worker_id' => $worker->id,
            'user_id' => $user->id,
            'type' => 'clock_in',
            'recorded_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('attendance-records.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AttendanceRecords/Index')
                ->has('attendanceRecords.data', 1)
            );
    }

    public function test_user_can_view_work_hours_report_with_worker_and_crew_totals(): void
    {
        $user = $this->authorizedUser();
        [, $dispatch, $worker] = $this->siteContext();

        AttendanceRecord::create([
            'dispatch_id' => $dispatch->id,
            'project_id' => $dispatch->project_id,
            'worker_id' => $worker->id,
            'user_id' => $user->id,
            'type' => 'clock_in',
            'recorded_at' => '2026-05-11 08:00:00',
        ]);
        AttendanceRecord::create([
            'dispatch_id' => $dispatch->id,
            'project_id' => $dispatch->project_id,
            'worker_id' => $worker->id,
            'user_id' => $user->id,
            'type' => 'clock_out',
            'worked_minutes' => 540,
            'recorded_at' => '2026-05-11 17:00:00',
        ]);
        AttendanceRecord::create([
            'dispatch_id' => $dispatch->id,
            'project_id' => $dispatch->project_id,
            'worker_id' => $worker->id,
            'user_id' => $user->id,
            'type' => 'clock_in',
            'recorded_at' => '2026-05-12 08:00:00',
            'requires_attention' => true,
            'anomaly_reason' => '未下工',
        ]);

        $this
            ->actingAs($user)
            ->get(route('reports.work-hours', [
                'period' => 'week',
                'date' => '2026-05-11',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/WorkHours')
                ->where('summary.worked_minutes', 540)
                ->where('summary.open_clock_in_count', 1)
                ->where('summary.anomaly_count', 1)
                ->has('workers', 1)
                ->has('crews', 1)
            );
    }


    public function test_worker_can_only_clock_assigned_dispatch(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $user = User::factory()->create();
        $role = \App\Models\Role::where('code', 'worker')->firstOrFail();
        $user->roles()->attach($role);
        [, $dispatch] = $this->siteContext();
        Worker::create([
            'user_id' => $user->id,
            'name' => '未指派師傅',
        ]);

        $this
            ->actingAs($user)
            ->post(route('attendance-records.store'), [
                'dispatch_id' => $dispatch->id,
                'type' => 'clock_in',
            ])
            ->assertForbidden();
    }

    private function siteContext(): array
    {
        $customer = Customer::create(['name' => '打卡客戶']);
        $crew = WorkCrew::create(['name' => 'GPS 工班']);
        $project = Project::create([
            'project_no' => 'TPH-2026-GPS1',
            'customer_id' => $customer->id,
            'work_crew_id' => $crew->id,
            'name' => '信義鐵皮屋',
            'status' => 'in_progress',
            'latitude' => 25.0330000,
            'longitude' => 121.5650000,
        ]);
        $dispatch = Dispatch::create([
            'project_id' => $project->id,
            'work_crew_id' => $crew->id,
            'work_item' => '現場施工',
            'status' => 'in_progress',
            'scheduled_date' => now()->toDateString(),
        ]);
        $worker = Worker::create([
            'work_crew_id' => $crew->id,
            'name' => 'GPS 師傅',
        ]);
        $dispatch->workers()->attach($worker->id);

        return [$project, $dispatch, $worker];
    }

    private function fakePng(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
        );

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
