<?php

namespace App\Services\Reports;

use App\Auth\CapabilityAuthorizer;
use App\Auth\DataScope;
use App\Models\Dispatch;
use App\Models\FinancialRecord;
use App\Models\Material;
use App\Models\ProgressLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly DataScope $dataScope,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(User $user): array
    {
        $today = Carbon::today();
        $widgets = [
            'dispatches' => $this->canAny($user, [
                'field.dispatches.view.tenant',
                'field.dispatches.view.assigned',
                'field.dispatches.view.own',
            ]),
            'progressLogs' => $this->canAny($user, [
                'field.progress_logs.view.tenant',
                'field.progress_logs.view.assigned',
                'field.progress_logs.view.own',
            ]),
            'projects' => $this->canAny($user, [
                'projects.projects.view.tenant',
                'projects.projects.view.assigned',
            ]),
            'financialRecords' => $this->authorizer->allows($user, 'finance.financial_records.view.tenant'),
            'materials' => $this->authorizer->allows($user, 'inventory.materials.view.tenant'),
        ];

        $todayDispatches = $widgets['dispatches']
            ? $this->visibleDispatches($user)
                ->with(['project:id,project_no,name,address,status', 'workCrew:id,name,leader_name'])
                ->withCount('workers')
                ->whereDate('scheduled_date', $today)
                ->orderBy('start_time')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Dispatch $dispatch) => [
                    'id' => $dispatch->id,
                    'work_item' => $dispatch->work_item,
                    'status' => $dispatch->status,
                    'scheduled_date' => $dispatch->scheduled_date?->toDateString(),
                    'start_time' => $this->timePayload($dispatch->start_time),
                    'end_time' => $this->timePayload($dispatch->end_time),
                    'project' => $dispatch->project,
                    'work_crew' => $dispatch->workCrew,
                    'workers_count' => $dispatch->workers_count,
                ])
            : collect();

        $todayProgressLogs = $widgets['progressLogs']
            ? $this->visibleProgressLogs($user)
                ->with(['project:id,project_no,name', 'dispatch:id,work_item', 'worker:id,name', 'creator:id,name'])
                ->withCount('photos')
                ->whereDate('work_date', $today)
                ->orderByDesc('progress_percent')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (ProgressLog $log) => [
                    'id' => $log->id,
                    'work_date' => $log->work_date?->toDateString(),
                    'project' => $log->project,
                    'dispatch' => $log->dispatch,
                    'worker' => $log->worker,
                    'creator' => $log->creator,
                    'work_items' => $log->work_items,
                    'progress_percent' => $log->progress_percent,
                    'photos_count' => $log->photos_count,
                ])
            : collect();

        $unpaidRecords = $widgets['financialRecords']
            ? FinancialRecord::query()
                ->with(['project.customer:id,name', 'project:id,project_no,name,customer_id'])
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->orderByRaw('due_date is null')
                ->orderBy('due_date')
                ->limit(8)
                ->get()
                ->map(fn (FinancialRecord $record) => $this->financialRecordPayload($record))
            : collect();

        $overdueRecords = $widgets['financialRecords']
            ? FinancialRecord::query()
                ->with(['project.customer:id,name', 'project:id,project_no,name,customer_id'])
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->whereDate('due_date', '<', $today)
                ->orderBy('due_date')
                ->limit(8)
                ->get()
                ->map(fn (FinancialRecord $record) => $this->financialRecordPayload($record))
            : collect();

        $lowStockMaterials = $widgets['materials']
            ? Material::query()
                ->with('category:id,name')
                ->whereColumn('current_stock', '<=', 'safe_stock')
                ->orderBy('current_stock')
                ->limit(8)
                ->get(['id', 'material_category_id', 'name', 'spec', 'unit', 'safe_stock', 'current_stock'])
            : collect();

        $projectStatusCounts = $widgets['projects']
            ? $this->visibleProjects($user)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => [
                    'status' => $row->status,
                    'label' => $this->projectStatuses()[$row->status] ?? $row->status,
                    'total' => (int) $row->total,
                ])
            : collect();

        return [
            'metrics' => [
                'today_dispatches' => $widgets['dispatches']
                    ? $this->visibleDispatches($user)->whereDate('scheduled_date', $today)->count()
                    : null,
                'today_progress_logs' => $widgets['progressLogs']
                    ? $this->visibleProgressLogs($user)->whereDate('work_date', $today)->count()
                    : null,
                'active_projects' => $widgets['projects']
                    ? $this->visibleProjects($user)->whereNotIn('status', ['closed', 'cancelled'])->count()
                    : null,
                'unpaid_amount' => $widgets['financialRecords']
                    ? FinancialRecord::query()->whereNotIn('status', ['paid', 'cancelled'])->sum('amount')
                    : null,
                'overdue_amount' => $widgets['financialRecords']
                    ? FinancialRecord::query()
                        ->whereNotIn('status', ['paid', 'cancelled'])
                        ->whereDate('due_date', '<', $today)
                        ->sum('amount')
                    : null,
                'low_stock_count' => $widgets['materials']
                    ? Material::query()->whereColumn('current_stock', '<=', 'safe_stock')->count()
                    : null,
            ],
            'widgets' => $widgets,
            'todayDispatches' => $todayDispatches,
            'todayProgressLogs' => $todayProgressLogs,
            'unpaidRecords' => $unpaidRecords,
            'overdueRecords' => $overdueRecords,
            'lowStockMaterials' => $lowStockMaterials,
            'projectStatusCounts' => $projectStatusCounts,
            'labels' => [
                'dispatchStatuses' => $this->dispatchStatuses(),
                'financialTypes' => $this->financialTypes(),
                'financialStatuses' => $this->financialStatuses(),
            ],
        ];
    }

    /**
     * @return Builder<Dispatch>
     */
    private function visibleDispatches(User $user): Builder
    {
        return $this->dataScope->dispatches(Dispatch::query(), $user);
    }

    /**
     * @return Builder<Project>
     */
    private function visibleProjects(User $user): Builder
    {
        return $this->dataScope->projects(Project::query(), $user);
    }

    /**
     * @return Builder<ProgressLog>
     */
    private function visibleProgressLogs(User $user): Builder
    {
        return $this->dataScope->progressLogs(ProgressLog::query(), $user);
    }

    /**
     * @param  array<int, string>  $capabilities
     */
    private function canAny(User $user, array $capabilities): bool
    {
        return collect($capabilities)->contains(fn (string $capability) => $this->authorizer->allows($user, $capability));
    }

    /**
     * @return array<string, string>
     */
    private function projectStatuses(): array
    {
        return [
            'inquiry' => '詢價',
            'estimating' => '估價中',
            'quoted' => '已報價',
            'contracted' => '已簽約',
            'preparing' => '備料中',
            'scheduled' => '待施工',
            'in_progress' => '施工中',
            'inspection' => '待驗收',
            'billing' => '待請款',
            'closed' => '已結案',
            'cancelled' => '已取消',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function dispatchStatuses(): array
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
     * @return array<string, string>
     */
    private function financialTypes(): array
    {
        return [
            'deposit' => '訂金',
            'progress' => '期中款',
            'final' => '尾款',
            'change_order' => '追加款',
            'reimbursement' => '代墊款',
            'deduction' => '扣款',
            'other' => '其他',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function financialStatuses(): array
    {
        return [
            'pending' => '待收款',
            'paid' => '已收款',
            'overdue' => '已逾期',
            'cancelled' => '已取消',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function financialRecordPayload(FinancialRecord $record): array
    {
        return [
            'id' => $record->id,
            'project' => $record->project,
            'type' => $record->type,
            'title' => $record->title,
            'amount' => $record->amount,
            'due_date' => $record->due_date?->toDateString(),
            'paid_date' => $record->paid_date?->toDateString(),
            'status' => $record->status,
            'is_overdue' => $record->status !== 'paid'
                && $record->status !== 'cancelled'
                && $record->due_date
                && $record->due_date->lt(Carbon::today()),
        ];
    }

    private function timePayload($value): ?string
    {
        return $value?->format('H:i');
    }
}
