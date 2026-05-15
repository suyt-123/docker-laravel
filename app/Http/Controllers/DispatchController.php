<?php

namespace App\Http\Controllers;

use App\Auth\DataScope;
use App\Http\Requests\StoreDispatchRequest;
use App\Http\Requests\UpdateDispatchRequest;
use App\Models\Dispatch;
use App\Models\Project;
use App\Models\WorkCrew;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DispatchController extends Controller
{
    public function __construct(private readonly DataScope $dataScope)
    {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $date = trim((string) $request->query('date', ''));

        $dispatches = $this->dataScope->dispatches(Dispatch::query(), $request->user())
            ->with(['project:id,project_no,name,address,status', 'workCrew:id,name,leader_name', 'creator:id,name'])
            ->withCount('workers')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('work_item', 'ilike', "%{$search}%")
                    ->orWhereHas('project', fn ($query) => $query
                        ->where('project_no', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('workCrew', fn ($query) => $query->where('name', 'ilike', "%{$search}%"));
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($date !== '', fn ($query) => $query->whereDate('scheduled_date', $date))
            ->orderByDesc('scheduled_date')
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Dispatch $dispatch) => [
                'id' => $dispatch->id,
                'project' => $dispatch->project,
                'work_crew' => $dispatch->workCrew,
                'creator' => $dispatch->creator,
                'work_item' => $dispatch->work_item,
                'status' => $dispatch->status,
                'scheduled_date' => $dispatch->scheduled_date?->toDateString(),
                'start_time' => $this->timePayload($dispatch->start_time),
                'end_time' => $this->timePayload($dispatch->end_time),
                'address' => $dispatch->address,
                'workers_count' => $dispatch->workers_count,
            ]);

        return Inertia::render('Dispatches/Index', [
            'dispatches' => $dispatches,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date' => $date,
            ],
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Dispatches/Create', [
            'options' => $this->formOptions(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function schedule(Request $request): Response
    {
        $start = filled($request->query('start'))
            ? Carbon::parse($request->query('start'))->startOfDay()
            : now()->startOfWeek();
        $days = max(1, min(45, (int) $request->query('days', 14)));
        $end = $start->copy()->addDays($days - 1)->endOfDay();

        $dispatches = $this->dataScope->dispatches(Dispatch::query(), $request->user())
            ->with(['project:id,project_no,name,status', 'workCrew:id,name', 'workers:id,name'])
            ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Dispatch $dispatch) => [
                'id' => $dispatch->id,
                'date' => $dispatch->scheduled_date?->toDateString(),
                'project' => $dispatch->project,
                'work_crew' => $dispatch->workCrew,
                'workers' => $dispatch->workers,
                'work_item' => $dispatch->work_item,
                'status' => $dispatch->status,
                'start_time' => $this->timePayload($dispatch->start_time),
                'end_time' => $this->timePayload($dispatch->end_time),
            ]);

        return Inertia::render('Dispatches/Schedule', [
            'days' => collect(range(0, $days - 1))->map(fn (int $offset) => [
                'date' => $start->copy()->addDays($offset)->toDateString(),
                'label' => $start->copy()->addDays($offset)->isoFormat('MM/DD ddd'),
            ]),
            'dispatches' => $dispatches,
            'filters' => [
                'start' => $start->toDateString(),
                'days' => $days,
            ],
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(StoreDispatchRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $workers = $data['workers'] ?? [];
        unset($data['workers']);
        $this->ensureNoScheduleConflict($data, $workers);

        $dispatch = Dispatch::create([
            ...$data,
            'created_by' => $request->user()?->id,
        ]);
        $this->syncWorkers($dispatch, $workers);

        return redirect()
            ->route('dispatches.show', $dispatch)
            ->with('success', '派工已建立。');
    }

    public function show(Dispatch $dispatch): Response
    {
        $dispatch->load([
            'project.customer:id,name',
            'project:id,customer_id,project_no,name,address,latitude,longitude',
            'workCrew:id,name,leader_name,phone',
            'creator:id,name',
            'workers' => fn ($query) => $query->with('workCrew:id,name')->orderBy('name'),
            'attendanceRecords' => fn ($query) => $query->with(['worker:id,name', 'user:id,name'])->latest('recorded_at')->limit(12),
        ]);

        return Inertia::render('Dispatches/Show', [
            'dispatch' => [
                'id' => $dispatch->id,
                'project' => $dispatch->project,
                'work_crew' => $dispatch->workCrew,
                'creator' => $dispatch->creator,
                'workers' => $dispatch->workers,
                'work_item' => $dispatch->work_item,
                'status' => $dispatch->status,
                'scheduled_date' => $dispatch->scheduled_date?->toDateString(),
                'start_time' => $this->timePayload($dispatch->start_time),
                'end_time' => $this->timePayload($dispatch->end_time),
                'address' => $dispatch->address,
                'instructions' => $dispatch->instructions,
                'attendance_records' => $dispatch->attendanceRecords->map(fn ($record) => [
                    'id' => $record->id,
                    'type' => $record->type,
                    'worked_minutes' => $record->worked_minutes,
                    'recorded_at' => $record->recorded_at?->toDateTimeString(),
                    'worker' => $record->worker,
                    'user' => $record->user,
                    'distance_meters' => $record->distance_meters,
                    'requires_attention' => $record->requires_attention,
                    'anomaly_reason' => $record->anomaly_reason,
                ])->values(),
            ],
            'statuses' => $this->statuses(),
            'attendanceTypes' => [
                'clock_in' => '上工',
                'clock_out' => '下工',
            ],
        ]);
    }

    public function edit(Dispatch $dispatch): Response
    {
        $dispatch->load('workers');

        return Inertia::render('Dispatches/Edit', [
            'dispatch' => [
                'id' => $dispatch->id,
                'project_id' => $dispatch->project_id,
                'work_crew_id' => $dispatch->work_crew_id,
                'work_item' => $dispatch->work_item,
                'status' => $dispatch->status,
                'scheduled_date' => $dispatch->scheduled_date?->toDateString(),
                'start_time' => $this->timePayload($dispatch->start_time),
                'end_time' => $this->timePayload($dispatch->end_time),
                'address' => $dispatch->address,
                'instructions' => $dispatch->instructions,
                'workers' => $dispatch->workers->map(fn ($worker) => [
                    'id' => $worker->id,
                    'hours' => $worker->pivot->hours,
                    'wage' => $worker->pivot->wage,
                    'note' => $worker->pivot->note,
                ])->values(),
            ],
            'options' => $this->formOptions(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateDispatchRequest $request, Dispatch $dispatch): RedirectResponse
    {
        $data = $request->validated();
        $workers = $data['workers'] ?? [];
        unset($data['workers']);
        $this->ensureNoScheduleConflict($data, $workers, $dispatch);

        $dispatch->update($data);
        $this->syncWorkers($dispatch, $workers);

        return redirect()
            ->route('dispatches.show', $dispatch)
            ->with('success', '派工已更新。');
    }

    public function destroy(Dispatch $dispatch): RedirectResponse
    {
        $dispatch->delete();

        return redirect()
            ->route('dispatches.index')
            ->with('success', '派工已刪除。');
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return [
            'scheduled' => '已排程',
            'notified' => '已通知',
            'in_progress' => '施工中',
            'completed' => '已完成',
            'cancelled' => '已取消',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'projects' => Project::query()
                ->with('customer:id,name')
                ->orderByDesc('id')
                ->get(['id', 'project_no', 'name', 'customer_id', 'address']),
            'workCrews' => WorkCrew::query()
                ->orderBy('name')
                ->get(['id', 'name', 'leader_name', 'phone']),
            'workers' => Worker::query()
                ->with('workCrew:id,name')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'work_crew_id', 'name', 'phone', 'role', 'daily_rate']),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $workers
     */
    private function syncWorkers(Dispatch $dispatch, array $workers): void
    {
        $sync = [];

        foreach ($workers as $worker) {
            $sync[$worker['id']] = [
                'hours' => $worker['hours'] ?? null,
                'wage' => $worker['wage'] ?? null,
                'note' => $worker['note'] ?? null,
            ];
        }

        $dispatch->workers()->sync($sync);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $workers
     */
    private function ensureNoScheduleConflict(array $data, array $workers, ?Dispatch $current = null): void
    {
        if (($data['status'] ?? null) === 'cancelled') {
            return;
        }

        $date = $data['scheduled_date'] ?? null;
        if (! $date) {
            return;
        }

        $baseQuery = Dispatch::query()
            ->whereDate('scheduled_date', $date)
            ->where('status', '!=', 'cancelled')
            ->when($current, fn (Builder $query) => $query->whereKeyNot($current->id));

        if (! empty($data['work_crew_id'])) {
            $crewConflict = (clone $baseQuery)
                ->with(['project:id,project_no,name', 'workCrew:id,name'])
                ->where('work_crew_id', $data['work_crew_id'])
                ->first();

            if ($crewConflict) {
                abort(422, '排程衝突：同一工班當天已有派工（'.$this->conflictLabel($crewConflict).'）。');
            }
        }

        $workerIds = collect($workers)
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        if ($workerIds->isEmpty()) {
            return;
        }

        $workerConflict = (clone $baseQuery)
            ->with(['project:id,project_no,name', 'workers:id,name'])
            ->whereHas('workers', fn (Builder $query) => $query->whereIn('workers.id', $workerIds))
            ->first();

        if ($workerConflict) {
            abort(422, '排程衝突：同一師傅當天已被派到其他工地（'.$this->conflictLabel($workerConflict).'）。');
        }
    }

    private function conflictLabel(Dispatch $dispatch): string
    {
        return trim(($dispatch->project?->project_no ? $dispatch->project->project_no.' · ' : '').$dispatch->work_item);
    }

    private function timePayload($value): ?string
    {
        return $value?->format('H:i');
    }
}
