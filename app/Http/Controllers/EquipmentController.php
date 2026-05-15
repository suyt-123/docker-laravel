<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEquipmentRequest;
use App\Http\Requests\UpdateEquipmentRequest;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Project;
use App\Models\WorkCrew;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EquipmentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $category = trim((string) $request->query('category', ''));

        $equipment = Equipment::query()
            ->with(['category:id,name,code', 'currentProject:id,project_no,name', 'currentWorker:id,name', 'currentWorkCrew:id,name'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('equipment_no', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")
                    ->orWhere('brand', 'ilike', "%{$search}%")
                    ->orWhere('model', 'ilike', "%{$search}%")
                    ->orWhere('serial_no', 'ilike', "%{$search}%")
                    ->orWhere('asset_tag', 'ilike', "%{$search}%");
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($category !== '', fn ($query) => $query->where('equipment_category_id', $category))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Equipment $equipment) => $this->equipmentPayload($equipment));

        return Inertia::render('Equipment/Index', [
            'equipment' => $equipment,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category' => $category,
            ],
            'categories' => $this->categories(),
            'statuses' => $this->statuses(),
            'conditions' => $this->conditions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Equipment/Create', [
            'equipment' => [
                'equipment_no' => $this->nextEquipmentNo(),
                'status' => 'available',
                'condition' => 'good',
            ],
            'options' => $this->formOptions(),
            'statuses' => $this->statuses(),
            'conditions' => $this->conditions(),
        ]);
    }

    public function store(StoreEquipmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['equipment_no'] = filled($data['equipment_no'] ?? null)
            ? $data['equipment_no']
            : $this->nextEquipmentNo();

        $equipment = Equipment::create($this->equipmentData($data));

        return redirect()
            ->route('equipment.show', $equipment)
            ->with('success', '工具與機具已建立。');
    }

    public function show(Equipment $equipment): Response
    {
        $equipment->load([
            'category:id,name,code',
            'currentProject:id,project_no,name',
            'currentWorker:id,name',
            'currentWorkCrew:id,name',
            'transactions' => fn ($query) => $query
                ->with(['project:id,project_no,name', 'worker:id,name', 'workCrew:id,name', 'handler:id,name'])
                ->latest('occurred_at')
                ->limit(20),
        ]);

        return Inertia::render('Equipment/Show', [
            'equipment' => [
                ...$this->equipmentPayload($equipment),
                'brand' => $equipment->brand,
                'model' => $equipment->model,
                'serial_no' => $equipment->serial_no,
                'asset_tag' => $equipment->asset_tag,
                'purchase_date' => $equipment->purchase_date?->toDateString(),
                'purchase_price' => $equipment->purchase_price,
                'warranty_until' => $equipment->warranty_until?->toDateString(),
                'last_maintenance_at' => $equipment->last_maintenance_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                'next_maintenance_at' => $equipment->next_maintenance_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                'note' => $equipment->note,
                'transactions' => $equipment->transactions->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'project' => $transaction->project,
                    'worker' => $transaction->worker,
                    'work_crew' => $transaction->workCrew,
                    'handler' => $transaction->handler,
                    'occurred_at' => $transaction->occurred_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                    'due_at' => $transaction->due_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                    'condition_before' => $transaction->condition_before,
                    'condition_after' => $transaction->condition_after,
                    'from_location' => $transaction->from_location,
                    'to_location' => $transaction->to_location,
                    'note' => $transaction->note,
                ]),
            ],
            'options' => $this->transactionOptions(),
            'statuses' => $this->statuses(),
            'conditions' => $this->conditions(),
            'transactionTypes' => $this->transactionTypes(),
        ]);
    }

    public function edit(Equipment $equipment): Response
    {
        return Inertia::render('Equipment/Edit', [
            'equipment' => [
                'id' => $equipment->id,
                'equipment_no' => $equipment->equipment_no,
                'equipment_category_id' => $equipment->equipment_category_id,
                'current_project_id' => $equipment->current_project_id,
                'current_worker_id' => $equipment->current_worker_id,
                'current_work_crew_id' => $equipment->current_work_crew_id,
                'name' => $equipment->name,
                'brand' => $equipment->brand,
                'model' => $equipment->model,
                'serial_no' => $equipment->serial_no,
                'asset_tag' => $equipment->asset_tag,
                'status' => $equipment->status,
                'condition' => $equipment->condition,
                'purchase_date' => $equipment->purchase_date?->toDateString(),
                'purchase_price' => $equipment->purchase_price,
                'warranty_until' => $equipment->warranty_until?->toDateString(),
                'last_maintenance_at' => $equipment->last_maintenance_at?->format('Y-m-d\TH:i'),
                'next_maintenance_at' => $equipment->next_maintenance_at?->format('Y-m-d\TH:i'),
                'note' => $equipment->note,
            ],
            'options' => $this->formOptions(),
            'statuses' => $this->statuses(),
            'conditions' => $this->conditions(),
        ]);
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment): RedirectResponse
    {
        $equipment->update($this->equipmentData($request->validated()));

        return redirect()
            ->route('equipment.show', $equipment)
            ->with('success', '工具與機具已更新。');
    }

    public function destroy(Equipment $equipment): RedirectResponse
    {
        abort_if($equipment->transactions()->exists(), 422, '已有交易紀錄的工具與機具不可刪除。');

        $equipment->delete();

        return redirect()
            ->route('equipment.index')
            ->with('success', '工具與機具已刪除。');
    }

    private function nextEquipmentNo(): string
    {
        $year = now()->format('Y');
        $prefix = "EQ-{$year}-";
        $lastNo = Equipment::query()
            ->where('equipment_no', 'like', "{$prefix}%")
            ->orderByDesc('equipment_no')
            ->value('equipment_no');
        $next = $lastNo ? ((int) substr($lastNo, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function categories()
    {
        return EquipmentCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function formOptions(): array
    {
        return [
            ...$this->transactionOptions(),
            'categories' => $this->categories(),
        ];
    }

    private function transactionOptions(): array
    {
        return [
            'projects' => Project::query()
                ->orderByDesc('id')
                ->get(['id', 'project_no', 'name'])
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'label' => "{$project->project_no} · {$project->name}",
                ]),
            'workers' => Worker::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'work_crew_id'])
                ->map(fn (Worker $worker) => [
                    'id' => $worker->id,
                    'label' => $worker->name,
                ]),
            'workCrews' => WorkCrew::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (WorkCrew $workCrew) => [
                    'id' => $workCrew->id,
                    'label' => $workCrew->name,
                ]),
        ];
    }

    private function equipmentData(array $data): array
    {
        return [
            ...$data,
            'purchase_price' => (int) ($data['purchase_price'] ?? 0),
        ];
    }

    private function equipmentPayload(Equipment $equipment): array
    {
        return [
            'id' => $equipment->id,
            'equipment_no' => $equipment->equipment_no,
            'name' => $equipment->name,
            'brand' => $equipment->brand,
            'model' => $equipment->model,
            'category' => $equipment->category,
            'status' => $equipment->status,
            'condition' => $equipment->condition,
            'current_project_id' => $equipment->current_project_id,
            'current_worker_id' => $equipment->current_worker_id,
            'current_work_crew_id' => $equipment->current_work_crew_id,
            'current_project' => $equipment->currentProject,
            'current_worker' => $equipment->currentWorker,
            'current_work_crew' => $equipment->currentWorkCrew,
            'next_maintenance_at' => $equipment->next_maintenance_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
        ];
    }

    private function statuses(): array
    {
        return [
            'available' => '可用',
            'assigned' => '配置工地',
            'borrowed' => '借出',
            'maintenance' => '維修中',
            'lost' => '遺失',
            'retired' => '報廢',
        ];
    }

    private function conditions(): array
    {
        return [
            'good' => '良好',
            'fair' => '可用但需注意',
            'damaged' => '損壞',
            'unsafe' => '不可使用',
        ];
    }

    private function transactionTypes(): array
    {
        return [
            'check_out' => '借出',
            'check_in' => '歸還',
            'assign_project' => '配置工地',
            'transfer_project' => '工地轉移',
            'maintenance_in' => '送修',
            'maintenance_out' => '維修完成',
            'lost' => '遺失',
            'retire' => '報廢',
            'adjust' => '狀態調整',
        ];
    }
}
