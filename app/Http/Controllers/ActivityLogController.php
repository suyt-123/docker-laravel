<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $module = trim((string) $request->query('module', ''));
        $action = trim((string) $request->query('action', ''));
        $actorId = trim((string) $request->query('actor_id', ''));
        $date = trim((string) $request->query('date', ''));

        $logs = ActivityLog::query()
            ->with('actor:id,name,email')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('subject_label', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhere('subject_type', 'ilike', "%{$search}%")
                    ->orWhereHas('actor', fn ($query) => $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%"));
            }))
            ->when($module !== '', fn ($query) => $query->where('module', $module))
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($actorId !== '', fn ($query) => $query->where('actor_id', $actorId))
            ->when($date !== '', fn ($query) => $query->whereDate('created_at', $date))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => $this->payload($log));

        return Inertia::render('ActivityLogs/Index', [
            'activityLogs' => $logs,
            'filters' => [
                'search' => $search,
                'module' => $module,
                'action' => $action,
                'actor_id' => $actorId,
                'date' => $date,
            ],
            'options' => [
                'modules' => $this->moduleOptions(),
                'actions' => $this->actions(),
                'actors' => User::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'email']),
            ],
        ]);
    }

    public function show(ActivityLog $activityLog): Response
    {
        $activityLog->load('actor:id,name,email');

        return Inertia::render('ActivityLogs/Show', [
            'activityLog' => $this->payload($activityLog, true),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function actions(): array
    {
        return [
            'create' => '新增',
            'update' => '編輯',
            'delete' => '刪除',
            'export_pdf' => '匯出 PDF',
            'assign_capabilities' => '指派權限',
            'login' => '登入',
            'logout' => '登出',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ActivityLog $log, bool $withValues = false): array
    {
        return [
            'id' => $log->id,
            'actor' => $log->actor,
            'action' => $log->action,
            'action_label' => $this->actions()[$log->action] ?? $log->action,
            'event' => $log->event,
            'subject_type' => $log->subject_type,
            'subject_name' => $log->subject_type ? class_basename($log->subject_type) : null,
            'subject_id' => $log->subject_id,
            'subject_label' => $log->subject_label,
            'module' => $log->module,
            'module_label' => $log->module ? $this->moduleLabel($log->module) : null,
            'description' => $log->description,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at?->toDateTimeString(),
            ...($withValues ? [
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'request_id' => $log->request_id,
            ] : []),
        ];
    }

    private function moduleLabel(string $module): string
    {
        return $this->modules()[$module] ?? Str::headline($module);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function moduleOptions()
    {
        return collect(array_keys($this->modules()))
            ->merge(ActivityLog::query()
                ->whereNotNull('module')
                ->distinct()
                ->pluck('module'))
            ->unique()
            ->sortBy(fn (string $module) => $this->moduleLabel($module))
            ->map(fn (string $module) => [
                'value' => $module,
                'label' => $this->moduleLabel($module),
            ])
            ->values();
    }

    /**
     * @return array<string, string>
     */
    private function modules(): array
    {
        return [
            'customers' => '客戶管理',
            'customer_contacts' => '客戶聯絡人',
            'projects' => '工程案件',
            'project_change_orders' => '工程變更追加單',
            'quotations' => '報價單',
            'quotation_items' => '報價項目',
            'quotation_templates' => '報價模板',
            'quotation_template_items' => '報價模板明細',
            'materials' => '材料管理',
            'material_categories' => '材料分類',
            'inventory_transactions' => '庫存異動',
            'equipment_categories' => '工具與機具分類',
            'equipment' => '工具與機具資產',
            'equipment_transactions' => '工具與機具交易',
            'suppliers' => '供應商管理',
            'purchase_orders' => '採購單',
            'purchase_order_items' => '採購單明細',
            'dispatches' => '派工管理',
            'attendance_records' => 'GPS 打卡',
            'work_crews' => '工班管理',
            'workers' => '師傅管理',
            'progress_logs' => '工程日誌',
            'progress_photos' => '工地照片',
            'financial_records' => '財務收款',
            'users' => '使用者管理',
            'roles' => '角色權限',
            'auth' => '登入登出',
        ];
    }
}
