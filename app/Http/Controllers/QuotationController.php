<?php

namespace App\Http\Controllers;

use App\Actions\Quotations\AcceptQuotationCustomer;
use App\Actions\Quotations\ApproveQuotation;
use App\Actions\Quotations\ConvertQuotationToProject;
use App\Actions\Quotations\DeclineQuotationCustomer;
use App\Actions\Quotations\RejectQuotation;
use App\Actions\Quotations\ReopenQuotation;
use App\Actions\Quotations\SendQuotationToCustomer;
use App\Actions\Quotations\SubmitQuotationForReview;
use App\Actions\Quotations\VoidQuotation;
use App\Auth\CapabilityAuthorizer;
use App\Auth\DataScope;
use App\Http\Requests\StoreQuotationRequest;
use App\Http\Requests\UpdateQuotationRequest;
use App\Models\DocumentAttachment;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationTemplate;
use App\Presenters\Quotations\QuotationPresenter;
use App\Services\Documents\DocumentAttachmentService;
use App\Services\Documents\QuotationPdfService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class QuotationController extends Controller
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly QuotationPdfService $quotationPdfs,
        private readonly DocumentAttachmentService $attachments,
        private readonly QuotationPresenter $quotationPresenter,
        private readonly DataScope $dataScope,
        private readonly ConvertQuotationToProject $convertQuotationToProject,
        private readonly ReopenQuotation $reopenQuotation,
        private readonly SubmitQuotationForReview $submitQuotationForReview,
        private readonly ApproveQuotation $approveQuotation,
        private readonly RejectQuotation $rejectQuotation,
        private readonly SendQuotationToCustomer $sendQuotationToCustomer,
        private readonly AcceptQuotationCustomer $acceptQuotationCustomer,
        private readonly DeclineQuotationCustomer $declineQuotationCustomer,
        private readonly VoidQuotation $voidQuotation,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

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
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Quotation $quotation) => $this->quotationPresenter->indexItem($quotation));

        return Inertia::render('Quotations/Index', [
            'quotations' => $quotations,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => $this->quotationPresenter->statuses(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Quotations/Create', [
            'options' => $this->quotationPresenter->formOptions($this->canViewCustomerContact($request)),
            'statuses' => $this->quotationPresenter->statuses(),
            'quotationNo' => $this->nextQuotationNo(),
        ]);
    }

    public function store(StoreQuotationRequest $request): RedirectResponse
    {
        $data = $this->quotationData($request->validated());
        $data['quotation_no'] = filled($data['quotation_no'] ?? null)
            ? $data['quotation_no']
            : $this->nextQuotationNo();
        $data['created_by'] = $request->user()?->id;
        $data['status'] = 'draft';

        $quotation = Quotation::create($data);
        $this->syncItems($quotation, $request->validated('items'));
        $this->logTemplateApplied($quotation);

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已建立。');
    }

    public function show(Request $request, Quotation $quotation): Response
    {
        $this->ensureVisible($request, $quotation);

        $canViewCustomerContact = $this->canViewCustomerContact($request);

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

        return Inertia::render('Quotations/Show', [
            'quotation' => $this->quotationPresenter->show($quotation),
            'statuses' => $this->quotationPresenter->statuses(),
        ]);
    }

    public function pdf(Request $request, Quotation $quotation): SymfonyResponse
    {
        $this->ensureVisible($request, $quotation);

        return $this->quotationPdfs->render($quotation, $request->user());
    }

    public function edit(Request $request, Quotation $quotation): Response
    {
        $this->ensureVisible($request, $quotation);
        abort_unless($this->canEditQuotation($quotation), 403);

        $quotation->load('items');

        return Inertia::render('Quotations/Edit', [
            'quotation' => $this->quotationPresenter->edit($quotation),
            'options' => $this->quotationPresenter->formOptions($this->canViewCustomerContact($request)),
            'statuses' => $this->quotationPresenter->statuses(),
        ]);
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);
        abort_unless($this->canEditQuotation($quotation), 403);

        $data = $this->quotationData($request->validated());
        $data['quotation_no'] = $quotation->quotation_no;
        $data['status'] = $quotation->status;

        $quotation->update($data);
        $this->syncItems($quotation, $request->validated('items'));

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已更新。');
    }

    public function submitReview(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);

        $quotation = $this->submitQuotationForReview->execute($quotation, $request->user());

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已送審。');
    }

    public function approve(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);

        $quotation = $this->approveQuotation->execute($quotation, $request->user());

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已核准。');
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);

        $quotation = $this->rejectQuotation->execute($quotation);

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已退回草稿。');
    }

    public function sendCustomer(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);

        $quotation = $this->sendQuotationToCustomer->execute($quotation);

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已標記為送客戶確認。');
    }

    public function acceptCustomer(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);

        $data = $request->validate([
            'customer_confirmed_by_name' => ['nullable', 'string', 'max:255'],
        ]);

        $quotation = $this->acceptQuotationCustomer->execute($quotation, $data, $request->user());

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已標記為客戶接受並鎖定。');
    }

    public function declineCustomer(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);

        $quotation = $this->declineQuotationCustomer->execute($quotation);

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已標記為客戶退回。');
    }

    public function convertProject(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);

        if ($quotation->project_id) {
            return redirect()
                ->route('projects.show', $quotation->project_id)
                ->with('success', '此報價單已綁定工程案件。');
        }

        $project = $this->convertQuotationToProject->execute($quotation, $request->user());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', '報價單已轉成工程案件。');
    }

    public function voidQuotation(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);

        $data = $request->validate([
            'void_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $quotation = $this->voidQuotation->execute($quotation, $data);

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已作廢。');
    }

    public function reopen(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);

        $newQuotation = $this->reopenQuotation->execute($quotation, $request->user());

        return redirect()
            ->route('quotations.show', $newQuotation)
            ->with('success', '已重開新版報價單。');
    }

    public function storeAttachment(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);
        abort_if($quotation->status === 'voided', 422, '已作廢報價單不可上傳附件。');

        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $data['file'];
        $attachment = $this->attachments->store($quotation, $file, $request->user(), $data['description'] ?? null);

        app(ActivityLogger::class)->log(
            'upload_attachment',
            'quotation.attachment_uploaded',
            $quotation,
            null,
            ['original_name' => $attachment->original_name],
            '報價單附件已上傳',
            'quotations',
        );

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '附件已上傳。');
    }

    public function destroyAttachment(Request $request, DocumentAttachment $documentAttachment): RedirectResponse
    {
        abort_unless($this->authorizer->allows($request->user(), 'sales.quotations.update.tenant'), 403);
        abort_unless($documentAttachment->attachable_type === Quotation::class, 404);

        /** @var Quotation $quotation */
        $quotation = $documentAttachment->attachable;
        abort_unless($quotation instanceof Quotation, 404);
        $this->ensureVisible($request, $quotation);

        $originalName = $documentAttachment->original_name;
        $this->attachments->delete($documentAttachment);

        app(ActivityLogger::class)->log(
            'delete_attachment',
            'quotation.attachment_deleted',
            $quotation,
            null,
            ['original_name' => $originalName],
            '報價單附件已刪除',
            'quotations',
        );

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '附件已刪除。');
    }

    public function destroy(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);
        abort_unless($quotation->status === 'draft', 422, '只有草稿報價單可以刪除。');

        $this->quotationPdfs->pruneCachedPdfs($quotation);

        $quotation->delete();

        return redirect()
            ->route('quotations.index')
            ->with('success', '報價單已刪除。');
    }

    private function canViewCustomerContact(Request $request): bool
    {
        return $this->authorizer->allows($request->user(), 'crm.customers.view_contact.tenant');
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

    private function canEditQuotation(Quotation $quotation): bool
    {
        return $quotation->status === 'draft' && $quotation->locked_at === null;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function quotationData(array $data): array
    {
        $items = collect($data['items']);
        $subtotal = $items->sum(fn ($item) => (int) round((float) $item['quantity'] * (int) $item['unit_price']));
        $tax = (int) ($data['tax'] ?? 0);
        $discount = (int) ($data['discount'] ?? 0);

        unset($data['items']);

        return [
            ...$data,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => max(0, $subtotal + $tax - $discount),
            'items_json' => $items->values()->all(),
            'template_inputs' => $data['template_inputs'] ?? null,
            'profit_rate' => $data['profit_rate'] ?? 0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Quotation $quotation, array $items): void
    {
        $quotation->items()->delete();

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (int) $item['unit_price'];

            $quotation->items()->create([
                'material_id' => $item['material_id'] ?? null,
                'name' => $item['name'],
                'spec' => $item['spec'] ?? null,
                'unit' => $item['unit'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'cost_price' => (int) ($item['cost_price'] ?? 0),
                'waste_rate' => $item['waste_rate'] ?? 0,
                'subtotal' => (int) round($quantity * $unitPrice),
                'note' => $item['note'] ?? null,
            ]);
        }
    }

    private function logTemplateApplied(Quotation $quotation): void
    {
        if (! $quotation->quotation_template_id) {
            return;
        }

        $template = QuotationTemplate::find($quotation->quotation_template_id);

        app(ActivityLogger::class)->log(
            'apply_template',
            'quotation_template.applied_to_quotation',
            $quotation,
            null,
            [
                'quotation_id' => $quotation->id,
                'quotation_no' => $quotation->quotation_no,
                'quotation_template_id' => $quotation->quotation_template_id,
                'quotation_template_name' => $template?->name,
                'template_inputs' => $quotation->template_inputs,
                'items_count' => $quotation->items()->count(),
            ],
            '報價模板已套用到報價單',
            'quotations',
        );
    }
}
