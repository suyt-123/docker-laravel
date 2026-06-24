<?php

namespace App\Http\Controllers\Api;

use App\Actions\Quotations\AcceptQuotationCustomer;
use App\Actions\Quotations\ApproveQuotation;
use App\Actions\Quotations\DeclineQuotationCustomer;
use App\Actions\Quotations\RejectQuotation;
use App\Actions\Quotations\ReopenQuotation;
use App\Actions\Quotations\SendQuotationToCustomer;
use App\Actions\Quotations\SubmitQuotationForReview;
use App\Actions\Quotations\VoidQuotation;
use App\Auth\CapabilityAuthorizer;
use App\Auth\DataScope;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Quotation;
use App\Presenters\Quotations\QuotationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly DataScope $dataScope,
        private readonly QuotationPresenter $quotationPresenter,
        private readonly ReopenQuotation $reopenQuotation,
        private readonly SubmitQuotationForReview $submitQuotationForReview,
        private readonly ApproveQuotation $approveQuotation,
        private readonly RejectQuotation $rejectQuotation,
        private readonly SendQuotationToCustomer $sendQuotationToCustomer,
        private readonly AcceptQuotationCustomer $acceptQuotationCustomer,
        private readonly DeclineQuotationCustomer $declineQuotationCustomer,
        private readonly VoidQuotation $voidQuotation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $perPage = min(max((int) $request->query('per_page', 12), 1), 100);

        $quotations = Quotation::query()
            ->with(['customer:id,name', 'project:id,project_no,name', 'creator:id,name', 'approver:id,name'])
            ->withCount('items')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('quotation_no', 'ilike', "%{$search}%")
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('project', fn ($query) => $query
                        ->where('project_no', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%"));
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Quotation $quotation) => $this->quotationPresenter->indexItem($quotation));

        return response()->json([
            'quotations' => $quotations,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => $this->quotationPresenter->statuses(),
        ]);
    }

    public function show(Request $request, Quotation $quotation): JsonResponse
    {
        $this->ensureVisible($request, $quotation);

        $canViewCustomerContact = $this->authorizer->allows($request->user(), 'crm.customers.view_contact.tenant');

        $quotation->load([
            'customer:id,name'.($canViewCustomerContact ? ',phone,line_id,address' : ''),
            'project:id,project_no,name,status,address',
            'creator:id,name',
            'approver:id,name',
            'items.material:id,name,spec,unit,cost_price,sale_price',
            'documentVersions.generator:id,name',
            'attachments.uploader:id,name',
            'reopenedFrom:id,quotation_no',
            'supersededBy:id,quotation_no',
        ]);

        return response()->json([
            'quotation' => $this->quotationPresenter->show($quotation),
            'statuses' => $this->quotationPresenter->statuses(),
        ]);
    }

    public function submitReview(Request $request, Quotation $quotation): JsonResponse
    {
        $this->ensureVisible($request, $quotation);

        return $this->quotationResponse(
            $request,
            $this->submitQuotationForReview->execute($quotation, $request->user()),
            '報價單已送審。',
        );
    }

    public function approve(Request $request, Quotation $quotation): JsonResponse
    {
        $this->ensureVisible($request, $quotation);

        return $this->quotationResponse(
            $request,
            $this->approveQuotation->execute($quotation, $request->user()),
            '報價單已核准。',
        );
    }

    public function reject(Request $request, Quotation $quotation): JsonResponse
    {
        $this->ensureVisible($request, $quotation);

        return $this->quotationResponse(
            $request,
            $this->rejectQuotation->execute($quotation),
            '報價單已退回草稿。',
        );
    }

    public function sendCustomer(Request $request, Quotation $quotation): JsonResponse
    {
        $this->ensureVisible($request, $quotation);

        return $this->quotationResponse(
            $request,
            $this->sendQuotationToCustomer->execute($quotation),
            '報價單已標記為送客戶確認。',
        );
    }

    public function acceptCustomer(Request $request, Quotation $quotation): JsonResponse
    {
        $this->ensureVisible($request, $quotation);

        $data = $request->validate([
            'customer_confirmed_by_name' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->quotationResponse(
            $request,
            $this->acceptQuotationCustomer->execute($quotation, $data, $request->user()),
            '報價單已標記為客戶接受並鎖定。',
        );
    }

    public function declineCustomer(Request $request, Quotation $quotation): JsonResponse
    {
        $this->ensureVisible($request, $quotation);

        return $this->quotationResponse(
            $request,
            $this->declineQuotationCustomer->execute($quotation),
            '報價單已標記為客戶退回。',
        );
    }

    public function voidQuotation(Request $request, Quotation $quotation): JsonResponse
    {
        $this->ensureVisible($request, $quotation);

        $data = $request->validate([
            'void_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->quotationResponse(
            $request,
            $this->voidQuotation->execute($quotation, $data),
            '報價單已作廢。',
        );
    }

    public function reopen(Request $request, Quotation $quotation): JsonResponse
    {
        $this->ensureVisible($request, $quotation);

        return $this->quotationResponse(
            $request,
            $this->reopenQuotation->execute($quotation, $request->user()),
            '已重開新版報價單。',
        );
    }

    private function ensureVisible(Request $request, Quotation $quotation): void
    {
        if (! $quotation->project_id) {
            return;
        }

        $visible = $this->dataScope
            ->projects(Project::query(), $request->user())
            ->whereKey($quotation->project_id)
            ->exists();

        abort_unless($visible, 403);
    }

    private function quotationResponse(Request $request, Quotation $quotation, string $message): JsonResponse
    {
        $canViewCustomerContact = $this->authorizer->allows($request->user(), 'crm.customers.view_contact.tenant');

        $quotation->load([
            'customer:id,name'.($canViewCustomerContact ? ',phone,line_id,address' : ''),
            'project:id,project_no,name,status,address',
            'creator:id,name',
            'approver:id,name',
            'items.material:id,name,spec,unit,cost_price,sale_price',
            'documentVersions.generator:id,name',
            'attachments.uploader:id,name',
            'reopenedFrom:id,quotation_no',
            'supersededBy:id,quotation_no',
        ]);

        return response()->json([
            'message' => $message,
            'quotation' => $this->quotationPresenter->show($quotation),
            'statuses' => $this->quotationPresenter->statuses(),
        ]);
    }
}
