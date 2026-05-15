<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEquipmentTransactionRequest;
use App\Models\Equipment;
use App\Models\EquipmentTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EquipmentTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', ''));

        $transactions = EquipmentTransaction::query()
            ->with(['equipment:id,equipment_no,name,status', 'project:id,project_no,name', 'worker:id,name', 'workCrew:id,name', 'handler:id,name'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('note', 'ilike', "%{$search}%")
                    ->orWhereHas('equipment', fn ($query) => $query
                        ->where('equipment_no', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('project', fn ($query) => $query
                        ->where('project_no', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('workCrew', fn ($query) => $query->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('worker', fn ($query) => $query->where('name', 'ilike', "%{$search}%"));
            }))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->latest('occurred_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (EquipmentTransaction $transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'equipment' => $transaction->equipment,
                'project' => $transaction->project,
                'worker' => $transaction->worker,
                'work_crew' => $transaction->workCrew,
                'handler' => $transaction->handler,
                'occurred_at' => $transaction->occurred_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                'due_at' => $transaction->due_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                'note' => $transaction->note,
            ]);

        return Inertia::render('EquipmentTransactions/Index', [
            'transactions' => $transactions,
            'filters' => [
                'search' => $search,
                'type' => $type,
            ],
            'types' => $this->types(),
        ]);
    }

    public function store(StoreEquipmentTransactionRequest $request, Equipment $equipment): RedirectResponse
    {
        DB::transaction(function () use ($request, $equipment) {
            $data = $request->validated();
            $data['equipment_id'] = $equipment->id;
            $data['handled_by'] = $request->user()?->id;
            $data['occurred_at'] = $data['occurred_at'] ?? now();
            $data['condition_before'] = $equipment->condition;
            $data['condition_after'] = $data['condition_after'] ?? $equipment->condition;

            EquipmentTransaction::create($data);

            $equipment->update($this->equipmentState($equipment, $data));
        });

        return redirect()
            ->route('equipment.show', $equipment)
            ->with('success', '工具與機具交易已記錄。');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function equipmentState(Equipment $equipment, array $data): array
    {
        $state = [
            'condition' => $data['condition_after'] ?? $equipment->condition,
        ];

        return match ($data['type']) {
            'check_out' => [
                ...$state,
                'status' => 'borrowed',
                'current_project_id' => $data['project_id'] ?? $equipment->current_project_id,
                'current_worker_id' => $data['worker_id'] ?? null,
                'current_work_crew_id' => $data['work_crew_id'] ?? $equipment->current_work_crew_id,
            ],
            'check_in' => [
                ...$state,
                'status' => 'available',
                'current_project_id' => null,
                'current_worker_id' => null,
                'current_work_crew_id' => null,
            ],
            'assign_project', 'transfer_project' => [
                ...$state,
                'status' => 'assigned',
                'current_project_id' => $data['project_id'] ?? null,
                'current_worker_id' => null,
                'current_work_crew_id' => $data['work_crew_id'] ?? null,
            ],
            'maintenance_in' => [
                ...$state,
                'status' => 'maintenance',
                'current_project_id' => null,
                'current_worker_id' => null,
                'current_work_crew_id' => null,
                'last_maintenance_at' => now(),
            ],
            'maintenance_out' => [
                ...$state,
                'status' => 'available',
                'current_project_id' => null,
                'current_worker_id' => null,
                'current_work_crew_id' => null,
                'last_maintenance_at' => now(),
            ],
            'lost' => [
                ...$state,
                'status' => 'lost',
            ],
            'retire' => [
                ...$state,
                'status' => 'retired',
                'current_project_id' => null,
                'current_worker_id' => null,
                'current_work_crew_id' => null,
            ],
            default => $state,
        };
    }

    private function types(): array
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
