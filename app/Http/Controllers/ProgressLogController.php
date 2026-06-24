<?php

namespace App\Http\Controllers;

use App\Auth\CapabilityAuthorizer;
use App\Auth\DataScope;
use App\Http\Requests\StoreProgressLogRequest;
use App\Http\Requests\UpdateProgressLogRequest;
use App\Models\Dispatch;
use App\Models\ProgressLog;
use App\Models\ProgressPhoto;
use App\Models\Project;
use App\Models\Worker;
use App\Services\Field\ProgressPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgressLogController extends Controller
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly DataScope $dataScope,
        private readonly ProgressPhotoService $photos,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $projectId = trim((string) $request->query('project_id', ''));
        $date = trim((string) $request->query('date', ''));

        $logs = $this->dataScope->progressLogs(ProgressLog::query(), $request->user())
            ->with(['project:id,project_no,name', 'dispatch:id,work_item,scheduled_date', 'worker:id,name', 'creator:id,name'])
            ->withCount('photos')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('work_items', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhere('issue', 'ilike', "%{$search}%")
                    ->orWhereHas('project', fn ($query) => $query
                        ->where('project_no', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('dispatch', fn ($query) => $query->where('work_item', 'ilike', "%{$search}%"));
            }))
            ->when($projectId !== '', fn ($query) => $query->where('project_id', $projectId))
            ->when($date !== '', fn ($query) => $query->whereDate('work_date', $date))
            ->orderByDesc('work_date')
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (ProgressLog $log) => $this->summaryPayload($log));

        return Inertia::render('ProgressLogs/Index', [
            'progressLogs' => $logs,
            'filters' => [
                'search' => $search,
                'project_id' => $projectId,
                'date' => $date,
            ],
            'options' => [
                'projects' => $this->visibleProjects($request)->get(['id', 'project_no', 'name']),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('ProgressLogs/Create', [
            'options' => $this->formOptions($request),
        ]);
    }

    public function store(StoreProgressLogRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureSubmittedScope($request, $data);
        unset($data['photos']);

        $log = ProgressLog::create([
            ...$data,
            'worker_count' => $data['worker_count'] ?? 0,
            'created_by' => $request->user()?->id,
        ]);

        $this->storePhotos($request, $log);

        return redirect()
            ->route('progress-logs.show', $log)
            ->with('success', '工程日誌已建立。');
    }

    public function show(ProgressLog $progressLog): Response
    {
        $this->ensureVisible(request(), $progressLog);

        $progressLog->load([
            'project:id,project_no,name,address',
            'dispatch:id,work_item,status,scheduled_date,start_time,end_time',
            'worker:id,name,role',
            'creator:id,name',
            'photos' => fn ($query) => $query->with('uploader:id,name')->latest(),
        ]);

        return Inertia::render('ProgressLogs/Show', [
            'progressLog' => $this->detailPayload($progressLog),
        ]);
    }

    public function edit(Request $request, ProgressLog $progressLog): Response
    {
        $this->ensureVisible($request, $progressLog);

        $progressLog->load('photos');

        return Inertia::render('ProgressLogs/Edit', [
            'progressLog' => [
                'id' => $progressLog->id,
                'project_id' => $progressLog->project_id,
                'dispatch_id' => $progressLog->dispatch_id,
                'worker_id' => $progressLog->worker_id,
                'work_date' => $progressLog->work_date?->toDateString(),
                'weather' => $progressLog->weather,
                'worker_count' => $progressLog->worker_count,
                'progress_percent' => $progressLog->progress_percent,
                'work_items' => $progressLog->work_items,
                'description' => $progressLog->description,
                'issue' => $progressLog->issue,
                'voice_text' => $progressLog->voice_text,
                'latitude' => $progressLog->latitude,
                'longitude' => $progressLog->longitude,
                'note' => $progressLog->note,
                'photos' => $progressLog->photos->map(fn (ProgressPhoto $photo) => $this->photoPayload($photo))->values(),
            ],
            'options' => $this->formOptions($request),
        ]);
    }

    public function update(UpdateProgressLogRequest $request, ProgressLog $progressLog): RedirectResponse
    {
        $this->ensureVisible($request, $progressLog);

        $data = $request->validated();
        $this->ensureSubmittedScope($request, $data);
        unset($data['photos']);

        $progressLog->update([
            ...$data,
            'worker_count' => $data['worker_count'] ?? 0,
        ]);

        $this->storePhotos($request, $progressLog);

        return redirect()
            ->route('progress-logs.show', $progressLog)
            ->with('success', '工程日誌已更新。');
    }

    public function destroy(ProgressLog $progressLog): RedirectResponse
    {
        $this->ensureVisible(request(), $progressLog);

        $this->photos->deleteForLog($progressLog);
        $progressLog->delete();

        return redirect()
            ->route('progress-logs.index')
            ->with('success', '工程日誌已刪除。');
    }

    public function destroyPhoto(ProgressPhoto $progressPhoto): RedirectResponse
    {
        $this->ensureVisible(request(), $progressPhoto->progressLog);

        $this->photos->delete($progressPhoto);

        return back()->with('success', '工地照片已刪除。');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'projects' => $this->visibleProjects($request)
                ->orderByDesc('id')
                ->get(['id', 'project_no', 'name', 'address']),
            'dispatches' => $this->visibleDispatches($request)
                ->with(['project:id,project_no,name', 'workCrew:id,name'])
                ->orderByDesc('scheduled_date')
                ->limit(200)
                ->get(['id', 'project_id', 'work_crew_id', 'work_item', 'scheduled_date']),
            'workers' => $this->dataScope->workers(Worker::query(), $request->user())
                ->with('workCrew:id,name')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'work_crew_id', 'name', 'role']),
        ];
    }

    private function visibleProjects(Request $request)
    {
        return $this->dataScope->projects(Project::query(), $request->user());
    }

    private function visibleDispatches(Request $request)
    {
        return $this->dataScope->dispatches(Dispatch::query(), $request->user());
    }

    private function ensureVisible(Request $request, ProgressLog $progressLog): void
    {
        abort_unless(
            $this->dataScope
                ->progressLogs(ProgressLog::query(), $request->user())
                ->whereKey($progressLog->id)
                ->exists(),
            403,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureSubmittedScope(Request $request, array $data): void
    {
        abort_unless(
            $this->visibleProjects($request)->whereKey($data['project_id'])->exists(),
            403,
        );

        if (! empty($data['dispatch_id'])) {
            abort_unless(
                $this->visibleDispatches($request)->whereKey($data['dispatch_id'])->exists(),
                403,
            );
        }

        if (! empty($data['worker_id'])) {
            abort_unless(
                $this->dataScope->workers(Worker::query(), $request->user())->whereKey($data['worker_id'])->exists(),
                403,
            );
        }
    }

    private function storePhotos(Request $request, ProgressLog $log): void
    {
        if (! config('features.progress_photos')) {
            return;
        }

        if (! $request->hasFile('photos')) {
            return;
        }

        $this->photos->storeForLog($log, $request->file('photos', []), $request->user());
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(ProgressLog $log): array
    {
        return [
            'id' => $log->id,
            'project' => $log->project,
            'dispatch' => $log->dispatch,
            'worker' => $log->worker,
            'creator' => $log->creator,
            'work_date' => $log->work_date?->toDateString(),
            'weather' => $log->weather,
            'worker_count' => $log->worker_count,
            'progress_percent' => $log->progress_percent,
            'work_items' => $log->work_items,
            'issue' => $log->issue,
            'photos_count' => $log->photos_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(ProgressLog $log): array
    {
        return [
            ...$this->summaryPayload($log),
            'description' => $log->description,
            'voice_text' => $log->voice_text,
            'latitude' => $log->latitude,
            'longitude' => $log->longitude,
            'note' => $log->note,
            'photos' => $log->photos->map(fn (ProgressPhoto $photo) => $this->photoPayload($photo))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function photoPayload(ProgressPhoto $photo): array
    {
        return [
            'id' => $photo->id,
            'file_path' => $photo->file_path,
            'url' => $this->photos->url($photo),
            'original_name' => $photo->original_name,
            'caption' => $photo->caption,
            'taken_at' => $photo->taken_at?->toDateTimeString(),
            'watermark_text' => $photo->watermark_text,
            'uploader' => $photo->uploader,
        ];
    }
}
