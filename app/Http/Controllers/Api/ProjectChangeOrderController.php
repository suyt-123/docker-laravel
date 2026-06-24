<?php

namespace App\Http\Controllers\Api;

use App\Actions\ProjectChangeOrders\ApproveProjectChangeOrder;
use App\Actions\ProjectChangeOrders\CancelProjectChangeOrder;
use App\Actions\ProjectChangeOrders\ConfirmProjectChangeOrderCustomer;
use App\Actions\ProjectChangeOrders\ConvertProjectChangeOrderToFinancialRecord;
use App\Actions\ProjectChangeOrders\CreateProjectChangeOrderQuotation;
use App\Actions\ProjectChangeOrders\SubmitProjectChangeOrderForReview;
use App\Auth\DataScope;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use App\Presenters\ProjectChangeOrders\ProjectChangeOrderPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $perPage = min(max((int) $request->query('per_page', 12), 1), 100);

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
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ProjectChangeOrder $order) => $this->projectChangeOrderPresenter->payload($order, $request->user()));

        return response()->json([
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => $this->projectChangeOrderPresenter->statuses(),
        ]);
    }

    public function show(Request $request, ProjectChangeOrder $projectChangeOrder): JsonResponse
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

        return response()->json([
            'order' => $this->projectChangeOrderPresenter->payload($projectChangeOrder, $request->user()),
            'statuses' => $this->projectChangeOrderPresenter->statuses(),
        ]);
    }

    public function submitReview(Request $request, ProjectChangeOrder $projectChangeOrder): JsonResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        return $this->orderResponse(
            $request,
            $this->submitProjectChangeOrderForReview->execute($projectChangeOrder, $request->user()),
            '追加單已送審。',
        );
    }

    public function approve(Request $request, ProjectChangeOrder $projectChangeOrder): JsonResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        return $this->orderResponse(
            $request,
            $this->approveProjectChangeOrder->execute($projectChangeOrder, $request->user()),
            '追加單已核准，等待客戶確認。',
        );
    }

    public function confirmCustomer(Request $request, ProjectChangeOrder $projectChangeOrder): JsonResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        return $this->orderResponse(
            $request,
            $this->confirmProjectChangeOrderCustomer->execute($projectChangeOrder, $request->user()),
            '追加單已標記為客戶確認。',
        );
    }

    public function cancel(Request $request, ProjectChangeOrder $projectChangeOrder): JsonResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        return $this->orderResponse(
            $request,
            $this->cancelProjectChangeOrder->execute($projectChangeOrder),
            '追加單已取消。',
        );
    }

    public function createQuotation(Request $request, ProjectChangeOrder $projectChangeOrder): JsonResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        $this->createProjectChangeOrderQuotation->execute($projectChangeOrder, $request->user());

        return $this->orderResponse(
            $request,
            $projectChangeOrder->refresh(),
            '已建立追加報價單，請依報價流程送審與核准。',
        );
    }

    public function convertFinancialRecord(Request $request, ProjectChangeOrder $projectChangeOrder): JsonResponse
    {
        $this->ensureVisible($request, $projectChangeOrder);

        [$projectChangeOrder] = $this->convertProjectChangeOrderToFinancialRecord->execute($projectChangeOrder);

        return $this->orderResponse(
            $request,
            $projectChangeOrder,
            '追加單已轉成追加款收款紀錄。',
        );
    }

    private function ensureVisible(Request $request, ProjectChangeOrder $projectChangeOrder): void
    {
        $visible = $this->dataScope
            ->projects(Project::query(), $request->user())
            ->whereKey($projectChangeOrder->project_id)
            ->exists();

        abort_unless($visible, 403);
    }

    private function orderResponse(Request $request, ProjectChangeOrder $projectChangeOrder, string $message): JsonResponse
    {
        $projectChangeOrder->load([
            'project.customer:id,name',
            'project:id,project_no,name,customer_id',
            'financialRecord:id,title,status,amount,due_date',
            'quotation:id,quotation_no,status,total',
            'creator:id,name',
            'approver:id,name',
        ]);

        return response()->json([
            'message' => $message,
            'order' => $this->projectChangeOrderPresenter->payload($projectChangeOrder, $request->user()),
            'statuses' => $this->projectChangeOrderPresenter->statuses(),
        ]);
    }
}
