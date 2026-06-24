<?php

namespace App\Services\Reports;

use App\Auth\DataScope;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;

class WorkHoursReportService
{
    public function __construct(private readonly DataScope $dataScope) {}

    /**
     * @return array<string, mixed>
     */
    public function report(User $user, ?string $periodInput, ?string $dateInput): array
    {
        $period = in_array($periodInput, ['day', 'week', 'month'], true)
            ? $periodInput
            : 'week';
        $date = filled($dateInput)
            ? Carbon::parse($dateInput)
            : now();

        [$start, $end] = match ($period) {
            'day' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            default => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
        };

        $records = $this->dataScope->attendanceRecords(AttendanceRecord::query(), $user)
            ->with(['worker.workCrew:id,name', 'project:id,project_no,name', 'dispatch:id,work_item'])
            ->whereBetween('recorded_at', [$start, $end])
            ->orderBy('recorded_at')
            ->get();

        $workerRows = $records
            ->groupBy(fn (AttendanceRecord $record) => $record->worker_id ?: 'unassigned')
            ->map(function ($items) {
                $worker = $items->first()->worker;

                return [
                    'worker' => $worker ? [
                        'id' => $worker->id,
                        'name' => $worker->name,
                        'work_crew' => $worker->workCrew,
                    ] : null,
                    'worked_minutes' => (int) $items->sum('worked_minutes'),
                    'worked_hours' => round((int) $items->sum('worked_minutes') / 60, 2),
                    'clock_in_count' => $items->where('type', 'clock_in')->count(),
                    'clock_out_count' => $items->where('type', 'clock_out')->count(),
                    'anomaly_count' => $items->where('requires_attention', true)->count(),
                    'open_clock_in_count' => $this->openClockInCount($items),
                ];
            })
            ->sortByDesc('worked_minutes')
            ->values();

        $crewRows = $workerRows
            ->groupBy(fn (array $row) => $row['worker']['work_crew']['id'] ?? 'unassigned')
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'work_crew' => $first['worker']['work_crew'] ?? null,
                    'worker_count' => $items->whereNotNull('worker')->count(),
                    'worked_minutes' => (int) $items->sum('worked_minutes'),
                    'worked_hours' => round((int) $items->sum('worked_minutes') / 60, 2),
                    'anomaly_count' => (int) $items->sum('anomaly_count'),
                    'open_clock_in_count' => (int) $items->sum('open_clock_in_count'),
                ];
            })
            ->sortByDesc('worked_minutes')
            ->values();

        return [
            'filters' => [
                'period' => $period,
                'date' => $date->toDateString(),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'summary' => [
                'worked_minutes' => (int) $records->sum('worked_minutes'),
                'worked_hours' => round((int) $records->sum('worked_minutes') / 60, 2),
                'clock_in_count' => $records->where('type', 'clock_in')->count(),
                'clock_out_count' => $records->where('type', 'clock_out')->count(),
                'anomaly_count' => $records->where('requires_attention', true)->count(),
                'open_clock_in_count' => (int) $workerRows->sum('open_clock_in_count'),
            ],
            'workers' => $workerRows,
            'crews' => $crewRows,
        ];
    }

    private function openClockInCount($records): int
    {
        return $records
            ->where('type', 'clock_in')
            ->filter(function (AttendanceRecord $clockIn) use ($records) {
                return ! $records->contains(function (AttendanceRecord $record) use ($clockIn) {
                    return $record->type === 'clock_out'
                        && (int) $record->dispatch_id === (int) $clockIn->dispatch_id
                        && (int) $record->worker_id === (int) $clockIn->worker_id
                        && $record->recorded_at->greaterThan($clockIn->recorded_at);
                });
            })
            ->count();
    }
}
