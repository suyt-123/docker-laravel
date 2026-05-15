<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\ProgressLog;
use App\Models\Project;
use App\Models\WorkCrew;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgressLogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_progress_log_pages(): void
    {
        $this->get(route('progress-logs.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_progress_log_index(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();

        ProgressLog::create([
            'project_id' => $project->id,
            'work_date' => '2026-06-01',
            'progress_percent' => 35,
            'work_items' => '屋頂骨架施工',
        ]);

        $this
            ->actingAs($user)
            ->get(route('progress-logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ProgressLogs/Index')
                ->has('progressLogs.data', 1)
            );
    }

    public function test_authenticated_user_can_create_progress_log_with_photos(): void
    {
        config(['features.progress_photos' => true]);
        Storage::fake('public');

        $user = $this->authorizedUser();
        [$project, $dispatch, $worker] = $this->siteContext();

        $this
            ->actingAs($user)
            ->post(route('progress-logs.store'), [
                'project_id' => $project->id,
                'dispatch_id' => $dispatch->id,
                'worker_id' => $worker->id,
                'work_date' => '2026-06-01',
                'weather' => '晴',
                'worker_count' => 3,
                'progress_percent' => 45,
                'work_items' => '浪板安裝',
                'description' => '完成 A 區屋頂浪板。',
                'photos' => [
                    $this->fakePng('site.png'),
                ],
            ])
            ->assertRedirect();

        $log = ProgressLog::where('work_items', '浪板安裝')->firstOrFail();

        $this->assertSame($user->id, $log->created_by);
        $this->assertDatabaseHas('progress_photos', [
            'progress_log_id' => $log->id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'original_name' => 'site.png',
        ]);

        Storage::disk('public')->assertExists($log->photos()->firstOrFail()->file_path);
    }

    public function test_progress_log_requires_project_date_and_progress_percent(): void
    {
        $user = $this->authorizedUser();

        $this
            ->actingAs($user)
            ->from(route('progress-logs.create'))
            ->post(route('progress-logs.store'), [
                'project_id' => '',
                'work_date' => '',
                'progress_percent' => '',
            ])
            ->assertRedirect(route('progress-logs.create'))
            ->assertSessionHasErrors(['project_id', 'work_date', 'progress_percent']);
    }

    public function test_authenticated_user_can_update_progress_log(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();
        $log = ProgressLog::create([
            'project_id' => $project->id,
            'work_date' => '2026-06-01',
            'progress_percent' => 10,
            'work_items' => '舊工項',
        ]);

        $this
            ->actingAs($user)
            ->patch(route('progress-logs.update', $log), [
                'project_id' => $project->id,
                'work_date' => '2026-06-02',
                'progress_percent' => 80,
                'work_items' => '收邊施工',
            ])
            ->assertRedirect(route('progress-logs.show', $log));

        $log->refresh();

        $this->assertSame('收邊施工', $log->work_items);
        $this->assertSame(80, $log->progress_percent);
    }

    public function test_authenticated_user_can_delete_progress_log_and_photo_file(): void
    {
        Storage::fake('public');

        $user = $this->authorizedUser();
        $project = $this->project();
        $log = ProgressLog::create([
            'project_id' => $project->id,
            'work_date' => '2026-06-01',
            'progress_percent' => 10,
        ]);
        $path = $this->fakePng('site.png')->store('progress-photos/test', 'public');
        $log->photos()->create([
            'project_id' => $project->id,
            'file_path' => $path,
        ]);

        $this
            ->actingAs($user)
            ->delete(route('progress-logs.destroy', $log))
            ->assertRedirect(route('progress-logs.index'));

        $this->assertDatabaseMissing('progress_logs', ['id' => $log->id]);
        Storage::disk('public')->assertMissing($path);
    }

    private function project(): Project
    {
        $customer = Customer::create(['name' => '工程日誌客戶']);

        return Project::create([
            'project_no' => 'TPH-2026-0001',
            'customer_id' => $customer->id,
            'name' => '五股鐵皮屋',
            'status' => 'in_progress',
        ]);
    }

    /**
     * @return array{0: Project, 1: Dispatch, 2: Worker}
     */
    private function siteContext(): array
    {
        $project = $this->project();
        $crew = WorkCrew::create(['name' => '北區鋼構班']);
        $worker = Worker::create([
            'work_crew_id' => $crew->id,
            'name' => '林師傅',
            'role' => '焊接',
        ]);
        $dispatch = Dispatch::create([
            'project_id' => $project->id,
            'work_crew_id' => $crew->id,
            'work_item' => '浪板安裝',
            'status' => 'in_progress',
            'scheduled_date' => '2026-06-01',
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
