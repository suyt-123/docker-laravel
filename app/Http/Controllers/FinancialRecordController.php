<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinancialRecordRequest;
use App\Http\Requests\UpdateFinancialRecordRequest;
use App\Models\FinancialRecord;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinancialRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $type = trim((string) $request->query('type', ''));

        $records = FinancialRecord::query()
            ->with(['project.customer:id,name', 'project:id,project_no,name,customer_id'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('title', 'ilike', "%{$search}%")
                    ->orWhereHas('project', fn ($query) => $query
                        ->where('project_no', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('project.customer', fn ($query) => $query->where('name', 'ilike', "%{$search}%"));
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (FinancialRecord $record) => $this->recordPayload($record));

        return Inertia::render('FinancialRecords/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'type' => $type,
            ],
            'types' => $this->types(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('FinancialRecords/Create', [
            'options' => $this->formOptions(),
            'types' => $this->types(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(StoreFinancialRecordRequest $request): RedirectResponse
    {
        $record = FinancialRecord::create($this->recordData($request->validated()));

        return redirect()
            ->route('financial-records.show', $record)
            ->with('success', '收款紀錄已建立。');
    }

    public function show(FinancialRecord $financialRecord): Response
    {
        $financialRecord->load(['project.customer:id,name', 'project:id,project_no,name,customer_id,contract_amount']);

        return Inertia::render('FinancialRecords/Show', [
            'record' => $this->recordPayload($financialRecord),
            'types' => $this->types(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function edit(FinancialRecord $financialRecord): Response
    {
        return Inertia::render('FinancialRecords/Edit', [
            'record' => [
                'id' => $financialRecord->id,
                'project_id' => $financialRecord->project_id,
                'type' => $financialRecord->type,
                'title' => $financialRecord->title,
                'amount' => $financialRecord->amount,
                'due_date' => $financialRecord->due_date?->toDateString(),
                'paid_date' => $financialRecord->paid_date?->toDateString(),
                'status' => $financialRecord->status,
                'note' => $financialRecord->note,
            ],
            'options' => $this->formOptions(),
            'types' => $this->types(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateFinancialRecordRequest $request, FinancialRecord $financialRecord): RedirectResponse
    {
        $financialRecord->update($this->recordData($request->validated()));

        return redirect()
            ->route('financial-records.show', $financialRecord)
            ->with('success', '收款紀錄已更新。');
    }

    public function destroy(FinancialRecord $financialRecord): RedirectResponse
    {
        $financialRecord->delete();

        return redirect()
            ->route('financial-records.index')
            ->with('success', '收款紀錄已刪除。');
    }

    /**
     * @return array<string, string>
     */
    private function types(): array
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
    private function statuses(): array
    {
        return [
            'pending' => '待收款',
            'paid' => '已收款',
            'overdue' => '已逾期',
            'cancelled' => '已取消',
        ];
    }

    private function formOptions(): array
    {
        return [
            'projects' => Project::query()
                ->with('customer:id,name')
                ->orderByDesc('id')
                ->get(['id', 'project_no', 'name', 'customer_id', 'contract_amount']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function recordData(array $data): array
    {
        if (($data['status'] ?? null) === 'paid' && blank($data['paid_date'] ?? null)) {
            $data['paid_date'] = now()->toDateString();
        }

        if (($data['status'] ?? null) === 'pending' && filled($data['due_date'] ?? null) && now()->toDateString() > $data['due_date']) {
            $data['status'] = 'overdue';
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function recordPayload(FinancialRecord $record): array
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
            'note' => $record->note,
            'is_overdue' => $record->status !== 'paid'
                && $record->status !== 'cancelled'
                && $record->due_date
                && $record->due_date->isPast(),
        ];
    }
}
