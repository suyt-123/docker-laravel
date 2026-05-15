<?php

namespace App\Http\Controllers;

use App\Auth\DataScope;
use App\Http\Requests\StoreAttendanceRecordRequest;
use App\Models\AttendanceRecord;
use App\Models\Dispatch;
use App\Models\Worker;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceRecordController extends Controller
{
    public function __construct(
        private readonly DataScope $dataScope,
        private readonly SettingService $settings,
    )
    {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', ''));
        $date = trim((string) $request->query('date', ''));
        $attention = $request->boolean('attention');

        $records = $this->dataScope->attendanceRecords(AttendanceRecord::query(), $request->user())
            ->with(['dispatch:id,work_item,scheduled_date', 'project:id,project_no,name', 'worker:id,name', 'user:id,name,email'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('anomaly_reason', 'ilike', "%{$search}%")
                    ->orWhere('note', 'ilike', "%{$search}%")
                    ->orWhereHas('project', fn ($query) => $query
                        ->where('project_no', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('dispatch', fn ($query) => $query->where('work_item', 'ilike', "%{$search}%"))
                    ->orWhereHas('worker', fn ($query) => $query->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('user', fn ($query) => $query->where('name', 'ilike', "%{$search}%"));
            }))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($date !== '', fn ($query) => $query->whereDate('recorded_at', $date))
            ->when($attention, fn ($query) => $query->where('requires_attention', true))
            ->latest('recorded_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AttendanceRecord $record) => $this->payload($record));

        return Inertia::render('AttendanceRecords/Index', [
            'attendanceRecords' => $records,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'date' => $date,
                'attention' => $attention,
            ],
            'types' => $this->types(),
        ]);
    }

    public function store(StoreAttendanceRecordRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $dispatch = $this->dataScope
            ->dispatches(Dispatch::query(), $request->user())
            ->with('project:id,latitude,longitude')
            ->whereKey($data['dispatch_id'])
            ->first();
        abort_unless($dispatch, 403);
        $worker = $this->resolveWorker($request, $data);
        $this->ensureWorkerIsAssigned($dispatch, $worker);

        $recordedAt = filled($data['recorded_at'] ?? null)
            ? Carbon::parse($data['recorded_at'])
            : now();
        $distance = $this->distanceMeters(
            $dispatch->project?->latitude,
            $dispatch->project?->longitude,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
        );
        $flags = $this->attendanceFlags($dispatch, $worker, $data['type'], $recordedAt, $distance);

        $record = AttendanceRecord::create([
            'dispatch_id' => $dispatch->id,
            'project_id' => $dispatch->project_id,
            'worker_id' => $worker?->id,
            'user_id' => $request->user()?->id,
            'type' => $data['type'],
            'worked_minutes' => $this->workedMinutes($dispatch, $worker, $data['type'], $recordedAt),
            'recorded_at' => $recordedAt,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'distance_meters' => $distance,
            'is_within_range' => $distance === null ? null : $distance <= $this->allowedDistanceMeters(),
            'is_duplicate' => $flags['is_duplicate'],
            'requires_attention' => $flags['requires_attention'],
            'anomaly_reason' => $flags['anomaly_reason'],
            'photo_path' => $request->file('photo')?->store('attendance-photos/'.now()->format('Y/m'), 'public'),
            'note' => $data['note'] ?? null,
        ]);

        return back()->with(
            'success',
            ($this->types()[$record->type] ?? '打卡').'已記錄'.($record->requires_attention ? '，請留意異常標記。' : '。'),
        );
    }

    public function show(Request $request, AttendanceRecord $attendanceRecord): Response
    {
        $this->ensureVisible($request, $attendanceRecord);
        $attendanceRecord->load(['dispatch:id,work_item,scheduled_date', 'project:id,project_no,name,address', 'worker:id,name,role', 'user:id,name,email']);

        return Inertia::render('AttendanceRecords/Show', [
            'attendanceRecord' => $this->payload($attendanceRecord, true),
            'types' => $this->types(),
        ]);
    }

    public function destroy(Request $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        $this->ensureVisible($request, $attendanceRecord);

        if ($attendanceRecord->photo_path) {
            Storage::disk('public')->delete($attendanceRecord->photo_path);
        }

        $attendanceRecord->delete();

        return redirect()
            ->route('attendance-records.index')
            ->with('success', '打卡紀錄已刪除。');
    }

    /**
     * @return array<string, string>
     */
    private function types(): array
    {
        return [
            'clock_in' => '上工',
            'clock_out' => '下工',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveWorker(Request $request, array $data): ?Worker
    {
        if (! empty($data['worker_id'])) {
            return $this->dataScope->workers(Worker::query(), $request->user())->whereKey($data['worker_id'])->firstOrFail();
        }

        return $request->user()?->worker;
    }

    private function ensureWorkerIsAssigned(Dispatch $dispatch, ?Worker $worker): void
    {
        if (! $worker) {
            return;
        }

        abort_unless(
            $dispatch->workers()->whereKey($worker->id)->exists()
                || (int) $dispatch->work_crew_id === (int) $worker->work_crew_id,
            403,
        );
    }

    /**
     * @return array{is_duplicate: bool, requires_attention: bool, anomaly_reason: string|null}
     */
    private function attendanceFlags(Dispatch $dispatch, ?Worker $worker, string $type, Carbon $recordedAt, ?int $distance): array
    {
        $query = AttendanceRecord::query()
            ->where('dispatch_id', $dispatch->id)
            ->whereDate('recorded_at', $recordedAt->toDateString())
            ->when($worker, fn ($query) => $query->where('worker_id', $worker->id));

        $isDuplicate = (clone $query)->where('type', $type)->exists();
        $reasons = [];

        $allowedDistance = $this->allowedDistanceMeters();

        if ($distance !== null && $distance > $allowedDistance) {
            $reasons[] = '離工地超過 '.$allowedDistance.' 公尺';
        }

        if ($isDuplicate) {
            $reasons[] = '重複'.($this->types()[$type] ?? '打卡');
        }

        if ($type === 'clock_out') {
            $hasClockIn = (clone $query)->where('type', 'clock_in')->exists();
            if (! $hasClockIn) {
                $reasons[] = '尚未上工打卡';
            }
        }

        if ($type === 'clock_in') {
            $hasOpenClockIn = (clone $query)->where('type', 'clock_in')->exists()
                && ! (clone $query)->where('type', 'clock_out')->exists();
            if ($hasOpenClockIn) {
                $reasons[] = '已有未下工紀錄';
            }
        }

        return [
            'is_duplicate' => $isDuplicate,
            'requires_attention' => $reasons !== [],
            'anomaly_reason' => $reasons === [] ? null : implode('、', $reasons),
        ];
    }

    private function distanceMeters($projectLat, $projectLng, $lat, $lng): ?int
    {
        if (! is_numeric($projectLat) || ! is_numeric($projectLng) || ! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $earthRadius = 6371000;
        $latFrom = deg2rad((float) $projectLat);
        $latTo = deg2rad((float) $lat);
        $latDelta = deg2rad((float) $lat - (float) $projectLat);
        $lngDelta = deg2rad((float) $lng - (float) $projectLng);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;

        return (int) round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function allowedDistanceMeters(): int
    {
        return max(0, $this->settings->integer('attendance.allowed_distance_meters'));
    }

    private function workedMinutes(Dispatch $dispatch, ?Worker $worker, string $type, Carbon $recordedAt): ?int
    {
        if ($type !== 'clock_out') {
            return null;
        }

        $clockIn = AttendanceRecord::query()
            ->where('dispatch_id', $dispatch->id)
            ->where('type', 'clock_in')
            ->where('recorded_at', '<=', $recordedAt)
            ->when($worker, fn ($query) => $query->where('worker_id', $worker->id))
            ->latest('recorded_at')
            ->first();

        return $clockIn ? max(0, $clockIn->recorded_at->diffInMinutes($recordedAt)) : null;
    }

    private function ensureVisible(Request $request, AttendanceRecord $attendanceRecord): void
    {
        abort_unless(
            $this->dataScope
                ->attendanceRecords(AttendanceRecord::query(), $request->user())
                ->whereKey($attendanceRecord->id)
                ->exists(),
            403,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AttendanceRecord $record, bool $detail = false): array
    {
        return [
            'id' => $record->id,
            'dispatch' => $record->dispatch,
            'project' => $record->project,
            'worker' => $record->worker,
            'user' => $record->user,
            'type' => $record->type,
            'worked_minutes' => $record->worked_minutes,
            'recorded_at' => $record->recorded_at?->toDateTimeString(),
            'latitude' => $record->latitude,
            'longitude' => $record->longitude,
            'distance_meters' => $record->distance_meters,
            'is_within_range' => $record->is_within_range,
            'is_duplicate' => $record->is_duplicate,
            'requires_attention' => $record->requires_attention,
            'anomaly_reason' => $record->anomaly_reason,
            'photo_url' => $record->photo_path ? Storage::disk('public')->url($record->photo_path) : null,
            ...($detail ? [
                'note' => $record->note,
                'photo_path' => $record->photo_path,
            ] : []),
        ];
    }
}
