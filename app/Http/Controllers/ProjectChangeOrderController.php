<?php

namespace App\Http\Controllers;

use App\Actions\ProjectChangeOrders\ApproveProjectChangeOrder;
use App\Actions\ProjectChangeOrders\CancelProjectChangeOrder;
use App\Actions\ProjectChangeOrders\ConfirmProjectChangeOrderCustomer;
use App\Actions\ProjectChangeOrders\ConvertProjectChangeOrderToFinancialRecord;
use App\Actions\ProjectChangeOrders\CreateProjectChangeOrderQuotation;
use App\Actions\ProjectChangeOrders\SubmitProjectChangeOrderForReview;
use App\Auth\DataScope;
use App\Http\Requests\StoreProjectChangeOrderRequest;
use App\Http\Requests\UpdateProjectChangeOrderRequest;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use App\Presenters\ProjectChangeOrders\ProjectChangeOrderPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectChangeOrderController extends Controller
{
    public function __construct(
        private readonly DataScope $dataScope,
        private readonly ProjectChangeOrderPresenter $projectChangeOrderPresenter,
        private readonly ConvertProjectChangeOrderToFinancialRecord $convertProjectChangeOrderToFinancialRecord,
        private readonly CreateProjectChangeOrderQuotation $createProjectChangeOrderQuotation,
        private readonly SubmitProjectChangeOrderForReview $submitProjectChangeOrderForReview,
        private readonly ApproveProjectChangeOrder $approveProjectChangeOrder,
        private readonly ConfirmProjectChangeOrderCustomer $confirmProjectChangeOrderCustomer,
        private readonly CancelProjectChangeOrder $cancelProjectChangeOrder,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $orders = ProjectChangeOrder::query()
            ->with(['project.customer:id,name', 'project:id,project_no,name,customer_id', 'financialRecord:id,status', 'quotation:id,quotation_no,status,total'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhereHas('project', fn ($query) => $query
                        ->where('project_no', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('project.customer', fn ($query) => $query->where('name', 'ilike', "%{$search}%"));
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (ProjectChangeOrder $order) => $this->projectChangeOrderPresenter->payload($order, $request->user()));

        return Inertia::render('ProjectChangeOrders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => $this->projectChangeOrderPresenter->statuses(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('ProjectChangeOrders/Create', [
            'order' => $this->projectChangeOrderPresenter->defaultFormData($request->query('project_id')),
            'options' => $this->projectChangeOrderPresenter->formOptions($request->user()),
            'statuses' => $this->projectChangeOrderPresenter->statuses(),
        ]);
    }

    public function store(StoreProjectChangeOrderRequest $request): RedirectResponse
    {
        $data = $this->orderData($request->validated());
        $data['created_by'] = $request->user()->id;

        $order = ProjectChangeOrder::create($data);

        return redirect()
            ->route('project-change-orders.show', $order)
            ->with('success', '工程變更追加單已建立。');
    }

    public function show(Request $request, ProjectChangeOrder $projectChangeOrder): Response
    {
        $this->ensureVisible($request, $projectChangeOrder);

        $projectChangeOrder->load([
            'project.customer:id,name',
            'project:id,project_no,name,customer_id',
            'financialRecord:id,title,status,amount,due_date',
            'quotation:id,quotation_no,status,total',
            'creator:id,name',
            'approver:id,name',
        ]);

        return Inertia::render('ProjectChangeOrders/Show', [
            'order' => $this->projectChangeOrderPresenter->payload($projectChangeOrder, $request->user()),
            'statuses' => $this->projectChangeOrderPresenter->statuses(),
        ]);
    }

    public function edit(Request $request, ProjectChangeOrder $projectChangeOrder): Response
    {
        $this->ensureVisible($request, $projectChangeOrder);

        abort_if($projectChangeOrder->financial_record_id !== null, 422, '已轉收款紀錄的追加單不可編輯。');
        abort_unless(in_array($projectChangeOrder->status, ['draft'], true), 422, '只有草稿追加單可以編輯。');

        return Inertia::render('ProjectChangeOrders/Edit', [
            'order' => $this->projectChangeOrderPresenter->form($projectChangeOrder),
            'options' => $this->projectChangeOrderPresenter->formOptions($request->user()),
            'statuses' => $this->projectChangeOrderPresenter->statuses(),
        ]);
    }

    public function update(UpdateProjectChangeOrderRequest $request, ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        abort_if($projectChangeOrder->financial_record_id !== null, 422, '已轉收款紀錄的追加單不可編輯。');
        abort_unless(in_array($projectChangeOrder->status, ['draft'], true), 422, '只有草稿追加單可以編輯。');

        $projectChangeOrder->update($this->orderData($request->validated()));

        return redirect()
            ->route('project-change-orders.show', $projectChangeOrder)
            ->with('success', '工程變更追加單已更新。');
    }

    public function destroy(Request $request, ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        abort_if($projectChangeOrder->financial_record_id !== null, 422, '已轉收款紀錄的追加單不可刪除。');
        abort_unless(in_array($projectChangeOrder->status, ['draft', 'cancelled'], true), 422, '只有草稿或已取消追加單可以刪除。');

        $projectChangeOrder->delete();

        return redirect()
            ->route('project-change-orders.index')
            ->with('success', '工程變更追加單已刪除。');
    }

    public function convertFinancialRecord(Request $request, ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        [, $record] = $this->convertProjectChangeOrderToFinancialRecord->execute($projectChangeOrder);

        return redirect()
            ->route('financial-records.show', $record)
            ->with('success', '追加單已轉成追加款收款紀錄。');
    }

    public function submitReview(Request $request, ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        $projectChangeOrder = $this->submitProjectChangeOrderForReview->execute($projectChangeOrder, $request->user());

        return redirect()
            ->route('project-change-orders.show', $projectChangeOrder)
            ->with('success', '追加單已送審。');
    }

    public function approve(Request $request, ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        $projectChangeOrder = $this->approveProjectChangeOrder->execute($projectChangeOrder, $request->user());

        return redirect()
            ->route('project-change-orders.show', $projectChangeOrder)
            ->with('success', '追加單已核准，等待客戶確認。');
    }

    public function confirmCustomer(Request $request, ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        $projectChangeOrder = $this->confirmProjectChangeOrderCustomer->execute($projectChangeOrder, $request->user());

        return redirect()
            ->route('project-change-orders.show', $projectChangeOrder)
            ->with('success', '追加單已標記為客戶確認。');
    }

    public function cancel(Request $request, ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        $projectChangeOrder = $this->cancelProjectChangeOrder->execute($projectChangeOrder);

        return redirect()
            ->route('project-change-orders.show', $projectChangeOrder)
            ->with('success', '追加單已取消。');
    }

    public function createQuotation(Request $request, ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        $quotation = $this->createProjectChangeOrderQuotation->execute($projectChangeOrder, $request->user());

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '已建立追加報價單，請依報價流程送審與核准。');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function orderData(array $data): array
    {
        return [
            ...$data,
            'amount' => (int) ($data['amount'] ?? 0),
            'requires_formal_quotation' => (bool) ($data['requires_formal_quotation'] ?? false),
        ];
    }

    private function ensureVisible(Request $request, ProjectChangeOrder $projectChangeOrder): void
    {
        $visible = $this->dataScope
            ->projects(Project::query(), $request->user())
            ->whereKey($projectChangeOrder->project_id)
            ->exists();

        abort_unless($visible, 403);
    }
}
