<?php

namespace App\Http\Controllers;

use App\Auth\CapabilityAuthorizer;
use App\Auth\DataScope;
use App\Http\Requests\StoreProjectChangeOrderRequest;
use App\Http\Requests\UpdateProjectChangeOrderRequest;
use App\Models\FinancialRecord;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use App\Models\Quotation;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProjectChangeOrderController extends Controller
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly DataScope $dataScope,
    ) {
    }

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
            ->through(fn (ProjectChangeOrder $order) => $this->orderPayload($order));

        return Inertia::render('ProjectChangeOrders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('ProjectChangeOrders/Create', [
            'order' => [
                'project_id' => $request->query('project_id', ''),
                'status' => 'draft',
                'requested_date' => now()->toDateString(),
                'requires_formal_quotation' => false,
            ],
            'options' => $this->formOptions($request),
            'statuses' => $this->statuses(),
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

    public function show(ProjectChangeOrder $projectChangeOrder): Response
    {
        $projectChangeOrder->load([
            'project.customer:id,name',
            'project:id,project_no,name,customer_id',
            'financialRecord:id,title,status,amount,due_date',
            'quotation:id,quotation_no,status,total',
            'creator:id,name',
            'approver:id,name',
        ]);

        return Inertia::render('ProjectChangeOrders/Show', [
            'order' => $this->orderPayload($projectChangeOrder),
            'statuses' => $this->statuses(),
        ]);
    }

    public function edit(Request $request, ProjectChangeOrder $projectChangeOrder): Response
    {
        abort_if($projectChangeOrder->financial_record_id !== null, 422, '已轉收款紀錄的追加單不可編輯。');
        abort_unless(in_array($projectChangeOrder->status, ['draft'], true), 422, '只有草稿追加單可以編輯。');

        return Inertia::render('ProjectChangeOrders/Edit', [
            'order' => [
                'id' => $projectChangeOrder->id,
                'project_id' => $projectChangeOrder->project_id,
                'quotation_id' => $projectChangeOrder->quotation_id,
                'title' => $projectChangeOrder->title,
                'description' => $projectChangeOrder->description,
                'amount' => $projectChangeOrder->amount,
                'requires_formal_quotation' => $projectChangeOrder->requires_formal_quotation,
                'requested_date' => $projectChangeOrder->requested_date?->toDateString(),
                'approved_date' => $projectChangeOrder->approved_date?->toDateString(),
                'due_date' => $projectChangeOrder->due_date?->toDateString(),
                'status' => $projectChangeOrder->status,
                'customer_note' => $projectChangeOrder->customer_note,
                'internal_note' => $projectChangeOrder->internal_note,
            ],
            'options' => $this->formOptions($request),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateProjectChangeOrderRequest $request, ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        abort_if($projectChangeOrder->financial_record_id !== null, 422, '已轉收款紀錄的追加單不可編輯。');
        abort_unless(in_array($projectChangeOrder->status, ['draft'], true), 422, '只有草稿追加單可以編輯。');

        $projectChangeOrder->update($this->orderData($request->validated()));

        return redirect()
            ->route('project-change-orders.show', $projectChangeOrder)
            ->with('success', '工程變更追加單已更新。');
    }

    public function destroy(ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        abort_if($projectChangeOrder->financial_record_id !== null, 422, '已轉收款紀錄的追加單不可刪除。');
        abort_unless(in_array($projectChangeOrder->status, ['draft', 'cancelled'], true), 422, '只有草稿或已取消追加單可以刪除。');

        $projectChangeOrder->delete();

        return redirect()
            ->route('project-change-orders.index')
            ->with('success', '工程變更追加單已刪除。');
    }

    public function convertFinancialRecord(Request $request, ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        abort_unless($projectChangeOrder->status === 'customer_confirmed', 422, '只有客戶已確認的追加單可以轉成追加款。');
        abort_if($projectChangeOrder->financial_record_id !== null, 422, '此追加單已轉成收款紀錄。');
        $this->ensureFormalQuotationReady($projectChangeOrder);

        [$order, $record] = DB::transaction(function () use ($projectChangeOrder) {
            $record = FinancialRecord::create([
                'project_id' => $projectChangeOrder->project_id,
                'project_change_order_id' => $projectChangeOrder->id,
                'type' => 'change_order',
                'title' => '追加款 - '.$projectChangeOrder->title,
                'amount' => $projectChangeOrder->amount,
                'due_date' => $projectChangeOrder->due_date,
                'status' => 'pending',
                'note' => $projectChangeOrder->customer_note ?: $projectChangeOrder->description,
            ]);

            $projectChangeOrder->update([
                'financial_record_id' => $record->id,
                'status' => 'converted',
                'converted_at' => now(),
            ]);

            return [$projectChangeOrder->refresh(), $record];
        });

        app(ActivityLogger::class)->log(
            'convert',
            'project_change_order.converted_to_financial_record',
            $order,
            null,
            [
                'project_change_order_id' => $order->id,
                'project_id' => $order->project_id,
                'financial_record_id' => $record->id,
                'amount' => $record->amount,
            ],
            '工程變更追加單已轉成追加款收款紀錄',
            'project_change_orders',
        );

        return redirect()
            ->route('financial-records.show', $record)
            ->with('success', '追加單已轉成追加款收款紀錄。');
    }

    public function submitReview(ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        abort_unless($projectChangeOrder->status === 'draft', 422, '只有草稿追加單可以送審。');

        $projectChangeOrder->update([
            'status' => 'pending_approval',
            'submitted_at' => now(),
        ]);

        $this->logWorkflow('submit_review', 'project_change_order.submitted_for_review', $projectChangeOrder->refresh(), '工程變更追加單已送主管核准');

        return redirect()
            ->route('project-change-orders.show', $projectChangeOrder)
            ->with('success', '追加單已送審。');
    }

    public function approve(Request $request, ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        abort_unless($projectChangeOrder->status === 'pending_approval', 422, '只有待主管核准的追加單可以核准。');

        $projectChangeOrder->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $this->logWorkflow('approve', 'project_change_order.approved', $projectChangeOrder->refresh(), '工程變更追加單已主管核准');

        return redirect()
            ->route('project-change-orders.show', $projectChangeOrder)
            ->with('success', '追加單已核准，等待客戶確認。');
    }

    public function confirmCustomer(ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        abort_unless($projectChangeOrder->status === 'approved', 422, '只有主管已核准的追加單可以標記客戶確認。');
        $this->ensureFormalQuotationReady($projectChangeOrder);

        $projectChangeOrder->update([
            'status' => 'customer_confirmed',
            'approved_date' => $projectChangeOrder->approved_date ?: now()->toDateString(),
            'customer_confirmed_at' => now(),
        ]);

        $this->logWorkflow('confirm_customer', 'project_change_order.customer_confirmed', $projectChangeOrder->refresh(), '工程變更追加單已客戶確認');

        return redirect()
            ->route('project-change-orders.show', $projectChangeOrder)
            ->with('success', '追加單已標記為客戶確認。');
    }

    public function cancel(ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        abort_if($projectChangeOrder->status === 'converted', 422, '已轉追加款的追加單不可取消。');

        $projectChangeOrder->update(['status' => 'cancelled']);

        $this->logWorkflow('cancel', 'project_change_order.cancelled', $projectChangeOrder->refresh(), '工程變更追加單已取消');

        return redirect()
            ->route('project-change-orders.show', $projectChangeOrder)
            ->with('success', '追加單已取消。');
    }

    public function createQuotation(Request $request, ProjectChangeOrder $projectChangeOrder): RedirectResponse
    {
        abort_if($projectChangeOrder->quotation_id !== null, 422, '此追加單已建立追加報價單。');
        abort_unless($projectChangeOrder->requires_formal_quotation, 422, '此追加單未設定需要正式報價。');
        abort_unless(in_array($projectChangeOrder->status, ['draft', 'pending_approval', 'approved'], true), 422, '此追加單目前不可建立追加報價單。');

        $projectChangeOrder->load('project.customer');

        $quotation = DB::transaction(function () use ($request, $projectChangeOrder) {
            $quotation = Quotation::create([
                'quotation_no' => $this->nextQuotationNo(),
                'customer_id' => $projectChangeOrder->project->customer_id,
                'project_id' => $projectChangeOrder->project_id,
                'created_by' => $request->user()?->id,
                'status' => 'draft',
                'subtotal' => $projectChangeOrder->amount,
                'tax' => 0,
                'discount' => 0,
                'total' => $projectChangeOrder->amount,
                'note' => "來源追加單：{$projectChangeOrder->title}",
                'items_json' => [
                    'source_project_change_order_id' => $projectChangeOrder->id,
                ],
            ]);

            $quotation->items()->create([
                'material_id' => null,
                'name' => $projectChangeOrder->title,
                'spec' => '工程變更追加',
                'unit' => '式',
                'quantity' => 1,
                'unit_price' => $projectChangeOrder->amount,
                'cost_price' => 0,
                'waste_rate' => 0,
                'subtotal' => $projectChangeOrder->amount,
                'note' => $projectChangeOrder->description,
            ]);

            $projectChangeOrder->update(['quotation_id' => $quotation->id]);

            return $quotation;
        });

        app(ActivityLogger::class)->log(
            'create_quotation',
            'project_change_order.quotation_created',
            $projectChangeOrder->refresh(),
            null,
            [
                'project_change_order_id' => $projectChangeOrder->id,
                'quotation_id' => $quotation->id,
                'quotation_no' => $quotation->quotation_no,
            ],
            '工程變更追加單已建立追加報價單',
            'project_change_orders',
        );

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '已建立追加報價單，請依報價流程送審與核准。');
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return [
            'draft' => '草稿',
            'pending_approval' => '待主管核准',
            'approved' => '主管已核准',
            'customer_confirmed' => '客戶已確認',
            'converted' => '已轉追加款',
            'cancelled' => '已取消',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'projects' => $this->dataScope
                ->projects(Project::query(), $request->user())
                ->with('customer:id,name')
                ->orderByDesc('id')
                ->get(['id', 'project_no', 'name', 'customer_id']),
            'quotations' => Quotation::query()
                ->with('customer:id,name')
                ->orderByDesc('id')
                ->limit(100)
                ->get(['id', 'quotation_no', 'customer_id', 'project_id', 'status', 'total']),
        ];
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

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(ProjectChangeOrder $order): array
    {
        return [
            'id' => $order->id,
            'project' => $order->project,
            'financial_record' => $order->financialRecord,
            'quotation' => $order->quotation,
            'creator' => $order->creator,
            'approver' => $order->approver,
            'title' => $order->title,
            'description' => $order->description,
            'amount' => $order->amount,
            'requires_formal_quotation' => $order->requires_formal_quotation,
            'requested_date' => $order->requested_date?->toDateString(),
            'submitted_at' => $order->submitted_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'approved_date' => $order->approved_date?->toDateString(),
            'approved_at' => $order->approved_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'customer_confirmed_at' => $order->customer_confirmed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'due_date' => $order->due_date?->toDateString(),
            'status' => $order->status,
            'customer_note' => $order->customer_note,
            'internal_note' => $order->internal_note,
            'converted_at' => $order->converted_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'can_submit_review' => $order->status === 'draft'
                && $this->authorizer->allows(request()->user(), 'projects.change_orders.submit_review.tenant'),
            'can_approve' => $order->status === 'pending_approval'
                && $this->authorizer->allows(request()->user(), 'projects.change_orders.approve.tenant'),
            'can_confirm_customer' => $order->status === 'approved'
                && $this->authorizer->allows(request()->user(), 'projects.change_orders.confirm_customer.tenant'),
            'can_cancel' => $order->status !== 'converted'
                && $this->authorizer->allows(request()->user(), 'projects.change_orders.cancel.tenant'),
            'can_create_quotation' => $order->requires_formal_quotation
                && $order->quotation_id === null
                && in_array($order->status, ['draft', 'pending_approval', 'approved'], true)
                && $this->authorizer->allows(request()->user(), 'projects.change_orders.create_quotation.tenant'),
            'can_convert' => $order->status === 'customer_confirmed'
                && $order->financial_record_id === null
                && $this->authorizer->allows(request()->user(), 'projects.change_orders.convert_financial_record.tenant'),
        ];
    }

    private function ensureFormalQuotationReady(ProjectChangeOrder $projectChangeOrder): void
    {
        if (! $projectChangeOrder->requires_formal_quotation) {
            return;
        }

        $projectChangeOrder->loadMissing('quotation');

        abort_unless($projectChangeOrder->quotation, 422, '此追加單需要正式報價，請先建立並核准追加報價單。');
        abort_unless($projectChangeOrder->quotation->status === 'approved', 422, '正式追加報價單尚未核准。');
    }

    private function nextQuotationNo(): string
    {
        $year = now()->format('Y');
        $prefix = "Q-{$year}-";
        $lastNo = Quotation::query()
            ->where('quotation_no', 'like', "{$prefix}%")
            ->orderByDesc('quotation_no')
            ->value('quotation_no');
        $next = $lastNo ? ((int) substr($lastNo, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function logWorkflow(string $action, string $event, ProjectChangeOrder $order, string $description): void
    {
        app(ActivityLogger::class)->log(
            $action,
            $event,
            $order,
            null,
            [
                'project_change_order_id' => $order->id,
                'project_id' => $order->project_id,
                'status' => $order->status,
            ],
            $description,
            'project_change_orders',
        );
    }
}
