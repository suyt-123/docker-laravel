<?php

namespace App\Http\Controllers;

use App\Auth\CapabilityAuthorizer;
use App\Auth\DataScope;
use App\Http\Requests\StoreQuotationRequest;
use App\Http\Requests\UpdateQuotationRequest;
use App\Models\DocumentAttachment;
use App\Models\DocumentVersion;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationTemplate;
use App\Services\SettingService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class QuotationController extends Controller
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly SettingService $settings,
        private readonly DataScope $dataScope,
    )
    {
    }

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
            ->through(fn (Quotation $quotation) => [
                'id' => $quotation->id,
                'quotation_no' => $quotation->quotation_no,
                'status' => $quotation->status,
                'customer' => $quotation->customer,
                'project' => $quotation->project,
                'creator' => $quotation->creator,
                'approver' => $quotation->approver,
                'subtotal' => $quotation->subtotal,
                'tax' => $quotation->tax,
                'discount' => $quotation->discount,
                'total' => $quotation->total,
                'profit_rate' => $quotation->profit_rate,
                'valid_until' => $quotation->valid_until?->toDateString(),
                'items_count' => $quotation->items_count,
            ]);

        return Inertia::render('Quotations/Index', [
            'quotations' => $quotations,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Quotations/Create', [
            'options' => $this->formOptions(),
            'statuses' => $this->statuses(),
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
            'quotation' => [
                'id' => $quotation->id,
                'quotation_no' => $quotation->quotation_no,
                'status' => $quotation->status,
                'customer_confirmation_status' => $quotation->customer_confirmation_status,
                'customer_sent_at' => $quotation->customer_sent_at?->toDateTimeString(),
                'customer_confirmed_at' => $quotation->customer_confirmed_at?->toDateTimeString(),
                'customer_confirmed_by_name' => $quotation->customer_confirmed_by_name,
                'locked_at' => $quotation->locked_at?->toDateTimeString(),
                'voided_at' => $quotation->voided_at?->toDateTimeString(),
                'void_reason' => $quotation->void_reason,
                'reopened_from' => $quotation->reopenedFrom,
                'superseded_by' => $quotation->supersededBy,
                'customer' => $quotation->customer,
                'project' => $quotation->project,
                'creator' => $quotation->creator,
                'approver' => $quotation->approver,
                'subtotal' => $quotation->subtotal,
                'tax' => $quotation->tax,
                'discount' => $quotation->discount,
                'total' => $quotation->total,
                'profit_rate' => $quotation->profit_rate,
                'valid_until' => $quotation->valid_until?->toDateString(),
                'note' => $quotation->note,
                'items' => $quotation->items,
                'document_versions' => $quotation->documentVersions
                    ->sortByDesc('version_number')
                    ->values()
                    ->map(fn (DocumentVersion $version) => [
                        'id' => $version->id,
                        'category' => $version->category,
                        'version_number' => $version->version_number,
                        'status' => $version->status,
                        'file_name' => $version->file_name,
                        'size' => $version->size,
                        'generated_at' => $version->generated_at?->toDateTimeString(),
                        'generator' => $version->generator,
                    ]),
                'attachments' => $quotation->attachments
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn (DocumentAttachment $attachment) => [
                        'id' => $attachment->id,
                        'original_name' => $attachment->original_name,
                        'mime_type' => $attachment->mime_type,
                        'size' => $attachment->size,
                        'description' => $attachment->description,
                        'created_at' => $attachment->created_at?->toDateTimeString(),
                        'uploader' => $attachment->uploader,
                        'url' => Storage::disk('public')->url($attachment->file_path),
                    ]),
            ],
            'statuses' => $this->statuses(),
        ]);
    }

    public function pdf(Request $request, Quotation $quotation): SymfonyResponse
    {
        $this->ensureVisible($request, $quotation);

        $quotation->load([
            'customer:id,name,phone,line_id,address,tax_id',
            'project:id,project_no,name,status,address',
            'creator:id,name',
            'approver:id,name',
            'items.material:id,name,spec,unit,cost_price,sale_price',
        ]);

        $statuses = $this->statuses();
        $settings = $this->settings->nested();
        $html = view('pdf.quotation', [
            'quotation' => $quotation,
            'statuses' => $statuses,
            'settings' => $settings,
        ])->render();

        if (config('documents.pdf_renderer') === 'html') {
            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="'.$quotation->quotation_no.'.html"',
            ]);
        }

        $directory = storage_path('app/pdf/quotations');
        File::ensureDirectoryExists($directory);

        $path = $this->quotationPdfCachePath($quotation, $directory, $settings, $statuses);

        if (File::exists($path)) {
            return response()
                ->file($path, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => $this->pdfDisposition().' filename="'.$quotation->quotation_no.'.pdf"',
                ]);
        }

        $tmpDirectory = storage_path('app/tmp');
        File::ensureDirectoryExists($tmpDirectory);
        File::ensureDirectoryExists($tmpDirectory.DIRECTORY_SEPARATOR.'chromium-profile');

        $footerHtml = <<<'HTML'
            <div style="width:100%; padding:0 12mm; color:#6b7280; font-family:'Noto Sans CJK TC','Noto Sans TC','DejaVu Sans',sans-serif; font-size:11px; text-align:center;">
                本報價單由系統產生，實際施工內容與付款條件以雙方確認版本為準。
                <span style="margin-left:8px;">第 <span class="pageNumber"></span> 頁 / 共 <span class="totalPages"></span> 頁</span>
            </div>
        HTML;

        Browsershot::html($html)
            ->setNodeBinary(config('services.browsershot.node_binary', '/usr/bin/node'))
            ->setChromePath(config('services.browsershot.chrome_path', '/usr/bin/chromium'))
            ->setUserDataDir($tmpDirectory.DIRECTORY_SEPARATOR.'chromium-profile')
            ->setEnvironmentOptions([
                'HOME' => '/tmp',
                'XDG_CACHE_HOME' => '/tmp',
                'XDG_CONFIG_HOME' => '/tmp',
            ])
            ->noSandbox()
            ->showBackground()
            ->showBrowserHeaderAndFooter()
            ->hideHeader()
            ->footerHtml($footerHtml)
            ->format('A4')
            ->margins(14, 12, 22, 12)
            ->timeout(180)
            ->protocolTimeout(180)
            ->addChromiumArguments([
                'disable-crash-reporter',
                'disable-crashpad',
                'disable-dev-shm-usage',
                'disable-gpu',
                'disable-setuid-sandbox',
                'no-zygote',
            ])
            ->save($path);

        $this->pruneQuotationPdfCache($quotation, $directory, $path);
        $this->recordDocumentVersion(
            $quotation,
            'quotation_pdf',
            $path,
            $quotation->quotation_no.'.pdf',
            [
                'total' => $quotation->total,
                'status' => $quotation->status,
                'customer_confirmation_status' => $quotation->customer_confirmation_status,
            ],
        );

        app(ActivityLogger::class)->log(
            'export_pdf',
            'quotation.pdf_exported',
            $quotation,
            null,
            [
                'quotation_no' => $quotation->quotation_no,
                'total' => $quotation->total,
                'filename' => $quotation->quotation_no.'.pdf',
            ],
            '報價單 PDF 已匯出',
            'quotations',
        );

        return response()
            ->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $this->pdfDisposition().' filename="'.$quotation->quotation_no.'.pdf"',
            ]);
    }

    public function edit(Request $request, Quotation $quotation): Response
    {
        $this->ensureVisible($request, $quotation);
        abort_unless($this->canEditQuotation($quotation), 403);

        $quotation->load('items');

        return Inertia::render('Quotations/Edit', [
            'quotation' => [
                'id' => $quotation->id,
                'quotation_no' => $quotation->quotation_no,
                'customer_id' => $quotation->customer_id,
                'project_id' => $quotation->project_id,
                'quotation_template_id' => $quotation->quotation_template_id,
                'template_inputs' => $quotation->template_inputs ?? [],
                'status' => $quotation->status,
                'tax' => $quotation->tax,
                'discount' => $quotation->discount,
                'profit_rate' => $quotation->profit_rate,
                'valid_until' => $quotation->valid_until?->toDateString(),
                'note' => $quotation->note,
                'items' => $quotation->items->map(fn ($item) => [
                    'material_id' => $item->material_id,
                    'name' => $item->name,
                    'spec' => $item->spec,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'cost_price' => $item->cost_price,
                    'waste_rate' => $item->waste_rate,
                    'note' => $item->note,
                ]),
            ],
            'options' => $this->formOptions(),
            'statuses' => $this->statuses(),
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
        abort_unless($quotation->status === 'draft', 422, '只有草稿報價單可以送審。');

        $oldStatus = $quotation->status;
        $quotation->update([
            'status' => 'reviewing',
            'approved_by' => null,
        ]);

        $this->logWorkflow(
            'submit_review',
            'quotation.submitted_for_review',
            $quotation,
            $oldStatus,
            'reviewing',
            '報價單已送審',
        );

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已送審。');
    }

    public function approve(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);
        abort_unless($quotation->status === 'reviewing', 422, '只有審核中的報價單可以核准。');

        $oldStatus = $quotation->status;
        $quotation->update([
            'status' => 'approved',
            'approved_by' => $request->user()?->id,
        ]);

        $this->logWorkflow(
            'approve',
            'quotation.approved',
            $quotation,
            $oldStatus,
            'approved',
            '報價單已核准',
        );

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已核准。');
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);
        abort_unless($quotation->status === 'reviewing', 422, '只有審核中的報價單可以退回。');

        $oldStatus = $quotation->status;
        $quotation->update([
            'status' => 'draft',
            'approved_by' => null,
        ]);

        $this->logWorkflow(
            'reject',
            'quotation.rejected',
            $quotation,
            $oldStatus,
            'draft',
            '報價單已退回草稿',
        );

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已退回草稿。');
    }

    public function sendCustomer(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);
        abort_unless($quotation->status === 'approved', 422, '只有已核准報價單可以送客戶確認。');

        $oldStatus = $quotation->status;
        $quotation->update([
            'status' => 'sent',
            'customer_confirmation_status' => 'pending',
            'customer_sent_at' => now(),
        ]);

        $this->logWorkflow(
            'send_customer',
            'quotation.sent_to_customer',
            $quotation,
            $oldStatus,
            'sent',
            '報價單已送客戶確認',
        );

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已標記為送客戶確認。');
    }

    public function acceptCustomer(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);
        abort_unless(in_array($quotation->status, ['approved', 'sent'], true), 422, '只有已核准或已送出的報價單可以標記客戶接受。');

        $data = $request->validate([
            'customer_confirmed_by_name' => ['nullable', 'string', 'max:255'],
        ]);

        $oldStatus = $quotation->status;
        $quotation->update([
            'status' => 'accepted',
            'customer_confirmation_status' => 'accepted',
            'customer_confirmed_at' => now(),
            'customer_confirmed_by_name' => $data['customer_confirmed_by_name'] ?? null,
            'locked_at' => now(),
        ]);

        $this->logWorkflow(
            'accept_customer',
            'quotation.customer_accepted',
            $quotation,
            $oldStatus,
            'accepted',
            '報價單已取得客戶接受',
        );

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已標記為客戶接受並鎖定。');
    }

    public function declineCustomer(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);
        abort_unless(in_array($quotation->status, ['approved', 'sent'], true), 422, '只有已核准或已送出的報價單可以標記客戶退回。');

        $oldStatus = $quotation->status;
        $quotation->update([
            'status' => 'rejected',
            'customer_confirmation_status' => 'rejected',
        ]);

        $this->logWorkflow(
            'decline_customer',
            'quotation.customer_rejected',
            $quotation,
            $oldStatus,
            'rejected',
            '報價單已標記為客戶退回',
        );

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

        abort_unless($quotation->status === 'accepted', 422, '只有客戶已接受的報價單可以轉工程案件。');

        $quotation->load(['customer:id,name', 'items']);

        $project = DB::transaction(function () use ($request, $quotation) {
            $estimatedCost = $quotation->items->sum(
                fn ($item) => (int) round((float) $item->quantity * (int) $item->cost_price),
            );

            $project = Project::create([
                'project_no' => $this->nextProjectNo(),
                'customer_id' => $quotation->customer_id,
                'manager_id' => $request->user()?->id,
                'name' => ($quotation->customer?->name ?: '未命名客戶').' - '.$quotation->quotation_no,
                'status' => 'contracted',
                'contract_amount' => $quotation->total,
                'estimated_cost' => $estimatedCost,
                'actual_cost' => 0,
                'gross_profit' => (int) $quotation->total - (int) $estimatedCost,
                'metadata' => [
                    'source_quotation_id' => $quotation->id,
                    'source_quotation_no' => $quotation->quotation_no,
                ],
            ]);

            $quotation->update(['project_id' => $project->id]);

            return $project;
        });

        app(ActivityLogger::class)->log(
            'convert_project',
            'quotation.converted_to_project',
            $quotation,
            ['project_id' => null],
            [
                'project_id' => $project->id,
                'project_no' => $project->project_no,
            ],
            '報價單已轉工程案件',
            'quotations',
        );

        return redirect()
            ->route('projects.show', $project)
            ->with('success', '報價單已轉成工程案件。');
    }

    public function voidQuotation(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);
        abort_if($quotation->project_id, 422, '已轉工程案件的報價單不可作廢。');
        abort_if($quotation->status === 'voided', 422, '此報價單已作廢。');

        $data = $request->validate([
            'void_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldStatus = $quotation->status;
        $quotation->update([
            'status' => 'voided',
            'voided_at' => now(),
            'void_reason' => $data['void_reason'] ?? null,
        ]);

        $this->logWorkflow(
            'void',
            'quotation.voided',
            $quotation,
            $oldStatus,
            'voided',
            '報價單已作廢',
        );

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', '報價單已作廢。');
    }

    public function reopen(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureVisible($request, $quotation);
        abort_if($quotation->project_id, 422, '已轉工程案件的報價單不可重開版本。');
        abort_unless(in_array($quotation->status, ['approved', 'sent', 'accepted', 'rejected', 'voided'], true), 422, '目前狀態不可重開版本。');

        $newQuotation = DB::transaction(function () use ($request, $quotation) {
            $quotation->load('items');
            $newQuotation = $quotation->replicate([
                'quotation_no',
                'status',
                'approved_by',
                'customer_confirmation_status',
                'customer_sent_at',
                'customer_confirmed_at',
                'customer_confirmed_by_name',
                'locked_at',
                'voided_at',
                'void_reason',
                'superseded_by_id',
                'created_at',
                'updated_at',
            ]);

            $newQuotation->forceFill([
                'quotation_no' => $this->nextQuotationNo(),
                'status' => 'draft',
                'approved_by' => null,
                'created_by' => $request->user()?->id,
                'customer_confirmation_status' => 'not_sent',
                'reopened_from_id' => $quotation->id,
            ])->save();

            foreach ($quotation->items as $item) {
                $newQuotation->items()->create($item->only([
                    'material_id',
                    'name',
                    'spec',
                    'unit',
                    'quantity',
                    'unit_price',
                    'cost_price',
                    'waste_rate',
                    'subtotal',
                    'note',
                ]));
            }

            $quotation->update(['superseded_by_id' => $newQuotation->id]);

            return $newQuotation;
        });

        app(ActivityLogger::class)->log(
            'reopen',
            'quotation.reopened',
            $quotation,
            null,
            [
                'new_quotation_id' => $newQuotation->id,
                'new_quotation_no' => $newQuotation->quotation_no,
            ],
            '報價單已重開新版本',
            'quotations',
        );

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
        $path = $file->store('quotation-attachments/'.now()->format('Y/m'), 'public');

        $quotation->attachments()->create([
            'uploaded_by' => $request->user()?->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'description' => $data['description'] ?? null,
        ]);

        app(ActivityLogger::class)->log(
            'upload_attachment',
            'quotation.attachment_uploaded',
            $quotation,
            null,
            ['original_name' => $file->getClientOriginalName()],
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

        Storage::disk('public')->delete($documentAttachment->file_path);
        $documentAttachment->delete();

        app(ActivityLogger::class)->log(
            'delete_attachment',
            'quotation.attachment_deleted',
            $quotation,
            null,
            ['original_name' => $documentAttachment->original_name],
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

        $this->pruneQuotationPdfCache($quotation, storage_path('app/pdf/quotations'));

        $quotation->delete();

        return redirect()
            ->route('quotations.index')
            ->with('success', '報價單已刪除。');
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return [
            'draft' => '草稿',
            'reviewing' => '審核中',
            'approved' => '已核准',
            'sent' => '已送出',
            'accepted' => '已接受',
            'rejected' => '已拒絕',
            'expired' => '已過期',
            'voided' => '已作廢',
        ];
    }

    private function pdfDisposition(): string
    {
        return config('documents.pdf_disposition') === 'attachment' ? 'attachment;' : 'inline;';
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $statuses
     */
    private function quotationPdfCachePath(Quotation $quotation, string $directory, array $settings, array $statuses): string
    {
        $version = sha1(json_encode([
            'quotation' => [
                'id' => $quotation->id,
                'quotation_no' => $quotation->quotation_no,
                'status' => $quotation->status,
                'subtotal' => $quotation->subtotal,
                'tax' => $quotation->tax,
                'discount' => $quotation->discount,
                'total' => $quotation->total,
                'valid_until' => $quotation->valid_until?->toDateString(),
                'note' => $quotation->note,
            ],
            'customer' => $quotation->customer?->only(['name', 'phone', 'line_id', 'address', 'tax_id']),
            'project' => $quotation->project?->only(['project_no', 'name', 'address']),
            'creator' => $quotation->creator?->only(['name']),
            'items' => $quotation->items
                ->map(fn ($item) => $item->only(['name', 'spec', 'unit', 'quantity', 'unit_price', 'subtotal', 'note']))
                ->values()
                ->all(),
            'settings' => [
                'company' => data_get($settings, 'company'),
                'quotation_default_terms' => data_get($settings, 'quotation.default_terms'),
            ],
            'statuses' => $statuses,
        ]));

        return $directory.DIRECTORY_SEPARATOR."quotation-{$quotation->id}-{$version}.pdf";
    }

    private function pruneQuotationPdfCache(Quotation $quotation, string $directory, ?string $currentPath = null): void
    {
        foreach (File::glob($directory.DIRECTORY_SEPARATOR."quotation-{$quotation->id}-*.pdf") as $cachedPath) {
            if ($cachedPath !== $currentPath) {
                File::delete($cachedPath);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordDocumentVersion(Quotation $quotation, string $category, string $path, string $fileName, array $metadata = []): void
    {
        $hash = File::exists($path) ? hash_file('sha256', $path) : null;
        $existing = DocumentVersion::query()
            ->where('document_type', Quotation::class)
            ->where('document_id', $quotation->id)
            ->where('category', $category)
            ->where('file_hash', $hash)
            ->first();

        if ($existing) {
            return;
        }

        DocumentVersion::query()
            ->where('document_type', Quotation::class)
            ->where('document_id', $quotation->id)
            ->where('category', $category)
            ->where('status', 'active')
            ->update(['status' => 'superseded']);

        $nextVersion = ((int) DocumentVersion::query()
            ->where('document_type', Quotation::class)
            ->where('document_id', $quotation->id)
            ->where('category', $category)
            ->max('version_number')) + 1;

        DocumentVersion::create([
            'document_type' => Quotation::class,
            'document_id' => $quotation->id,
            'category' => $category,
            'version_number' => $nextVersion,
            'status' => 'active',
            'file_path' => $path,
            'file_name' => $fileName,
            'size' => File::exists($path) ? File::size($path) : 0,
            'file_hash' => $hash,
            'generated_at' => now(),
            'generated_by' => request()->user()?->id,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $customerColumns = ['id', 'name'];
        if ($this->authorizer->allows(request()->user(), 'crm.customers.view_contact.tenant')) {
            $customerColumns[] = 'phone';
        }

        return [
            'customers' => Customer::query()->orderBy('name')->get($customerColumns),
            'projects' => Project::query()
                ->with('customer:id,name')
                ->orderByDesc('id')
                ->get(['id', 'project_no', 'name', 'customer_id']),
            'materials' => Material::query()
                ->with('category:id,name')
                ->orderBy('name')
                ->get(['id', 'material_category_id', 'name', 'spec', 'unit', 'cost_price', 'sale_price']),
            'quotationTemplates' => QuotationTemplate::query()
                ->where('status', 'active')
                ->with('items')
                ->orderBy('name')
                ->get()
                ->map(fn (QuotationTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'type' => $template->type,
                    'profit_rate' => $template->profit_rate,
                    'tax' => $template->tax,
                    'discount' => $template->discount,
                    'parameter_definitions' => $template->parameter_definitions ?? [],
                    'note' => $template->note,
                ]),
        ];
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

    private function nextProjectNo(): string
    {
        $year = now()->format('Y');
        $prefix = "TPH-{$year}-";
        $lastNo = Project::query()
            ->where('project_no', 'like', "{$prefix}%")
            ->orderByDesc('project_no')
            ->value('project_no');
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

    private function logWorkflow(
        string $action,
        string $event,
        Quotation $quotation,
        string $oldStatus,
        string $newStatus,
        string $description,
    ): void {
        app(ActivityLogger::class)->log(
            $action,
            $event,
            $quotation,
            ['status' => $oldStatus],
            [
                'status' => $newStatus,
                'approved_by' => $quotation->approved_by,
            ],
            $description,
            'quotations',
        );
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
