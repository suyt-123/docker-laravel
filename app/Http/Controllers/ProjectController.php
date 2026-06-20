<?php

namespace App\Http\Controllers;

use App\Auth\CapabilityAuthorizer;
use App\Auth\DataScope;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\DocumentVersion;
use App\Models\FinancialRecord;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use App\Models\User;
use App\Models\WorkCrew;
use App\Services\SettingService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProjectController extends Controller
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly DataScope $dataScope,
        private readonly SettingService $settings,
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $canViewFinancials = $this->canViewFinancials($request);
        $canViewCustomerContact = $this->canViewCustomerContact($request);

        $projects = $this->dataScope->projects(Project::query(), $request->user())
            ->with([
                'customer:id,name'.($canViewCustomerContact ? ',phone' : ''),
                'manager:id,name',
                'workCrew:id,name',
            ])
            ->withCount(['quotations', 'dispatches', 'financialRecords'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('project_no', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")
                    ->orWhere('type', 'ilike', "%{$search}%")
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'ilike', "%{$search}%"));
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Project $project) => [
                'id' => $project->id,
                'project_no' => $project->project_no,
                'name' => $project->name,
                'type' => $project->type,
                'status' => $project->status,
                'customer' => $project->customer,
                'manager' => $project->manager,
                'work_crew' => $project->workCrew,
                'address' => $project->address,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                ...$this->projectFinancialPayload($project, $canViewFinancials),
                'quotations_count' => $project->quotations_count,
                'dispatches_count' => $project->dispatches_count,
                'financial_records_count' => $project->financial_records_count,
            ]);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Projects/Create', [
            'options' => $this->formOptions(),
            'statuses' => $this->statuses(),
            'projectNo' => $this->nextProjectNo(),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if (! $this->canViewFinancials($request)) {
            $data['contract_amount'] = 0;
            $data['estimated_cost'] = 0;
            $data['actual_cost'] = 0;
        }
        $data['project_no'] = filled($data['project_no'] ?? null)
            ? $data['project_no']
            : $this->nextProjectNo();
        $data['gross_profit'] = $this->grossProfit($data);

        $project = Project::create($data);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', '工程案件已建立。');
    }

    public function show(Request $request, Project $project): Response
    {
        $this->ensureVisible($request, $project);

        $canViewFinancials = $this->canViewFinancials($request);
        $canViewQuotations = $this->authorizer->allows($request->user(), 'sales.quotations.view.tenant');
        $canViewChangeOrders = $this->authorizer->allows($request->user(), 'projects.change_orders.view.tenant');
        $canViewFinancialRecords = $this->authorizer->allows($request->user(), 'finance.financial_records.view.tenant');
        $canExportFinancialRecords = $this->authorizer->allows($request->user(), 'finance.financial_records.export_pdf.tenant');
        $canViewInventoryTransactions = $this->authorizer->allows($request->user(), 'inventory.inventory_transactions.view.tenant');
        $canViewProgressLogs = $this->canAny($request, [
            'field.progress_logs.view.tenant',
            'field.progress_logs.view.assigned',
            'field.progress_logs.view.own',
        ]);
        $canViewCustomerContact = $this->canViewCustomerContact($request);

        $project->load([
            'customer:id,name'.($canViewCustomerContact ? ',phone,line_id,address' : ''),
            'manager:id,name',
            'workCrew:id,name,leader_name,phone',
            'dispatches' => fn ($query) => $query->with('workCrew:id,name')->latest()->limit(8),
            ...($canViewQuotations ? [
                'quotations' => fn ($query) => $query->latest()->limit(8),
            ] : []),
            ...($canViewChangeOrders ? [
                'changeOrders' => fn ($query) => $query->with('financialRecord:id,title,status')->latest()->limit(8),
            ] : []),
            ...($canViewFinancialRecords ? [
                'financialRecords' => fn ($query) => $query->latest()->limit(20),
                'documentVersions' => fn ($query) => $query
                    ->with('generator:id,name')
                    ->where('category', 'invoice_pdf')
                    ->latest()
                    ->limit(8),
            ] : []),
            ...($canViewInventoryTransactions ? [
                'inventoryTransactions' => fn ($query) => $query->with('material:id,name,spec,unit')->latest()->limit(8),
            ] : []),
        ]);

        $progressLogs = $canViewProgressLogs
            ? $this->dataScope
                ->progressLogs($project->progressLogs()->getQuery(), $request->user())
                ->with(['dispatch:id,work_item', 'worker:id,name', 'creator:id,name'])
                ->withCount('photos')
                ->latest()
                ->limit(8)
                ->get()
            : collect();

        return Inertia::render('Projects/Show', [
            'project' => [
                'id' => $project->id,
                'project_no' => $project->project_no,
                'name' => $project->name,
                'type' => $project->type,
                'status' => $project->status,
                'address' => $project->address,
                'latitude' => $project->latitude,
                'longitude' => $project->longitude,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                ...$this->projectFinancialPayload($project, $canViewFinancials),
                'customer' => $project->customer,
                'manager' => $project->manager,
                'work_crew' => $project->workCrew,
                'quotations' => $canViewQuotations ? $project->quotations : [],
                'change_orders' => $canViewChangeOrders
                    ? $project->changeOrders->map(fn (ProjectChangeOrder $order) => $this->changeOrderPayload($order))->values()
                    : [],
                'change_order_statuses' => $this->changeOrderStatuses(),
                'dispatches' => $project->dispatches,
                'progress_logs' => $canViewProgressLogs
                    ? $progressLogs->map(fn ($log) => [
                        'id' => $log->id,
                        'work_date' => $log->work_date?->toDateString(),
                        'dispatch' => $log->dispatch,
                        'worker' => $log->worker,
                        'creator' => $log->creator,
                        'work_items' => $log->work_items,
                        'progress_percent' => $log->progress_percent,
                        'photos_count' => $log->photos_count,
                    ])->values()
                    : [],
                'financial_records' => $canViewFinancialRecords
                    ? $project->financialRecords->map(fn (FinancialRecord $record) => $this->financialRecordPayload($record))->values()
                    : [],
                'invoice_document_versions' => $canViewFinancialRecords
                    ? $project->documentVersions->map(fn (DocumentVersion $version) => [
                        'id' => $version->id,
                        'version_number' => $version->version_number,
                        'status' => $version->status,
                        'file_name' => $version->file_name,
                        'size' => $version->size,
                        'generated_at' => $version->generated_at?->toDateTimeString(),
                        'generator' => $version->generator,
                    ])->values()
                    : [],
                'financial_record_types' => $this->financialRecordTypes(),
                'financial_record_statuses' => $this->financialRecordStatuses(),
                'can_export_invoice_pdf' => $canExportFinancialRecords,
                'can_create_change_order' => $this->authorizer->allows($request->user(), 'projects.change_orders.create.tenant'),
                'inventory_transactions' => $canViewInventoryTransactions ? $project->inventoryTransactions : [],
            ],
            'statuses' => $this->statuses(),
        ]);
    }

    public function invoicePdf(Request $request, Project $project): SymfonyResponse
    {
        $this->ensureVisible($request, $project);

        $data = $request->validate([
            'financial_record_ids' => ['required', 'array', 'min:1'],
            'financial_record_ids.*' => ['integer', 'distinct'],
        ]);

        $ids = collect($data['financial_record_ids'])->map(fn ($id) => (int) $id)->values();
        $project->load('customer:id,name,phone,line_id,address,tax_id');

        $records = FinancialRecord::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $ids)
            ->whereIn('status', ['pending', 'overdue'])
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->get();

        abort_unless($records->count() === $ids->count(), 422, '請款單只能包含同一工程的待收或逾期款項。');

        $total = (int) $records->sum('amount');
        $html = view('pdf.invoice', [
            'project' => $project,
            'records' => $records,
            'types' => $this->financialRecordTypes(),
            'statuses' => $this->financialRecordStatuses(),
            'settings' => $this->settings->nested(),
            'total' => $total,
            'issuedAt' => now(),
        ])->render();

        if (config('documents.pdf_renderer') === 'html') {
            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="'.$project->project_no.'-invoice.html"',
            ]);
        }

        $directory = storage_path('app/pdf/invoices');
        File::ensureDirectoryExists($directory);
        $tmpDirectory = storage_path('app/tmp');
        File::ensureDirectoryExists($tmpDirectory);
        File::ensureDirectoryExists($tmpDirectory.DIRECTORY_SEPARATOR.'chromium-profile');

        $path = $directory.DIRECTORY_SEPARATOR.$project->project_no.'-invoice-'.now()->format('YmdHis').'-'.uniqid().'.pdf';
        $footerHtml = <<<'HTML'
            <div style="width:100%; padding:0 12mm; color:#6b7280; font-family:'Noto Sans CJK TC','Noto Sans TC','DejaVu Sans',sans-serif; font-size:11px; text-align:center;">
                本請款單由系統產生，實際付款條件以雙方確認版本為準。
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

        $this->recordDocumentVersion(
            $project,
            'invoice_pdf',
            $path,
            $project->project_no.'-invoice.pdf',
            [
                'financial_record_ids' => $ids->all(),
                'total_amount' => $total,
                'issued_at' => now()->toDateString(),
            ],
        );

        app(ActivityLogger::class)->log(
            'export_pdf',
            'financial_record.invoice_pdf_exported',
            $project,
            null,
            [
                'project_id' => $project->id,
                'project_no' => $project->project_no,
                'financial_record_ids' => $ids->all(),
                'total_amount' => $total,
            ],
            '合併請款單 PDF 已匯出',
            'financial_records',
        );

        return response()
            ->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $this->pdfDisposition().' filename="'.$project->project_no.'-invoice.pdf"',
            ]);
    }

    public function edit(Request $request, Project $project): Response
    {
        $this->ensureVisible($request, $project);

        $canViewFinancials = $this->canViewFinancials($request);

        return Inertia::render('Projects/Edit', [
            'project' => [
                'id' => $project->id,
                'project_no' => $project->project_no,
                'customer_id' => $project->customer_id,
                'manager_id' => $project->manager_id,
                'work_crew_id' => $project->work_crew_id,
                'name' => $project->name,
                'type' => $project->type,
                'status' => $project->status,
                'address' => $project->address,
                'latitude' => $project->latitude,
                'longitude' => $project->longitude,
                'start_date' => $project->start_date?->toDateString(),
                'end_date' => $project->end_date?->toDateString(),
                ...$this->projectFinancialPayload($project, $canViewFinancials),
            ],
            'options' => $this->formOptions(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->ensureVisible($request, $project);

        $data = $request->validated();
        $data['project_no'] = $project->project_no;
        if (! $this->canViewFinancials($request)) {
            $data['contract_amount'] = $project->contract_amount;
            $data['estimated_cost'] = $project->estimated_cost;
            $data['actual_cost'] = $project->actual_cost;
        }
        $data['gross_profit'] = $this->grossProfit($data);

        $project->update($data);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', '工程案件已更新。');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->ensureVisible($request, $project);

        $photoPaths = $project->progressPhotos()
            ->pluck('file_path')
            ->filter()
            ->all();
        $attendancePhotoPaths = $project->attendanceRecords()
            ->pluck('photo_path')
            ->filter()
            ->all();

        $project->delete();

        Storage::disk('public')->delete([
            ...$photoPaths,
            ...$attendancePhotoPaths,
        ]);

        return redirect()
            ->route('projects.index')
            ->with('success', '工程案件已刪除。');
    }

    private function ensureVisible(Request $request, Project $project): void
    {
        $visible = $this->dataScope
            ->projects(Project::query(), $request->user())
            ->whereKey($project->id)
            ->exists();

        abort_unless($visible, 403);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordDocumentVersion(Project $project, string $category, string $path, string $fileName, array $metadata = []): void
    {
        $hash = File::exists($path) ? hash_file('sha256', $path) : null;
        $existing = DocumentVersion::query()
            ->where('document_type', Project::class)
            ->where('document_id', $project->id)
            ->where('category', $category)
            ->where('file_hash', $hash)
            ->first();

        if ($existing) {
            return;
        }

        DocumentVersion::query()
            ->where('document_type', Project::class)
            ->where('document_id', $project->id)
            ->where('category', $category)
            ->where('status', 'active')
            ->update(['status' => 'superseded']);

        $nextVersion = ((int) DocumentVersion::query()
            ->where('document_type', Project::class)
            ->where('document_id', $project->id)
            ->where('category', $category)
            ->max('version_number')) + 1;

        DocumentVersion::create([
            'document_type' => Project::class,
            'document_id' => $project->id,
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
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return [
            'inquiry' => '詢價',
            'estimating' => '估價中',
            'quoted' => '已報價',
            'contracted' => '已簽約',
            'preparing' => '備料中',
            'scheduled' => '待施工',
            'in_progress' => '施工中',
            'inspection' => '待驗收',
            'billing' => '待請款',
            'closed' => '已結案',
            'cancelled' => '已取消',
        ];
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
            'customers' => Customer::query()
                ->orderBy('name')
                ->get($customerColumns),
            'managers' => User::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'workCrews' => WorkCrew::query()
                ->orderBy('name')
                ->get(['id', 'name', 'leader_name']),
        ];
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function grossProfit(array $data): int
    {
        return (int) ($data['contract_amount'] ?? 0) - (int) ($data['actual_cost'] ?? 0);
    }

    private function canViewFinancials(Request $request): bool
    {
        return $this->authorizer->allows($request->user(), 'projects.projects.view_financials.tenant');
    }

    private function canViewCustomerContact(Request $request): bool
    {
        return $this->authorizer->allows($request->user(), 'crm.customers.view_contact.tenant');
    }

    /**
     * @param  array<int, string>  $capabilities
     */
    private function canAny(Request $request, array $capabilities): bool
    {
        return collect($capabilities)->contains(fn (string $capability) => $this->authorizer->allows($request->user(), $capability));
    }

    private function pdfDisposition(): string
    {
        return config('documents.pdf_disposition') === 'attachment' ? 'attachment;' : 'inline;';
    }

    /**
     * @return array<string, string>
     */
    private function financialRecordTypes(): array
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
    private function financialRecordStatuses(): array
    {
        return [
            'pending' => '待收款',
            'paid' => '已收款',
            'overdue' => '已逾期',
            'cancelled' => '已取消',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function changeOrderStatuses(): array
    {
        return [
            'pending' => '待客戶確認',
            'approved' => '客戶已確認',
            'rejected' => '已取消',
            'converted' => '已轉追加款',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function financialRecordPayload(FinancialRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => $record->type,
            'title' => $record->title,
            'amount' => $record->amount,
            'due_date' => $record->due_date?->toDateString(),
            'paid_date' => $record->paid_date?->toDateString(),
            'status' => $record->status,
            'note' => $record->note,
            'invoice_eligible' => in_array($record->status, ['pending', 'overdue'], true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function changeOrderPayload(ProjectChangeOrder $order): array
    {
        return [
            'id' => $order->id,
            'title' => $order->title,
            'amount' => $order->amount,
            'requested_date' => $order->requested_date?->toDateString(),
            'approved_date' => $order->approved_date?->toDateString(),
            'due_date' => $order->due_date?->toDateString(),
            'status' => $order->status,
            'financial_record' => $order->financialRecord,
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function projectFinancialPayload(Project $project, bool $canViewFinancials): array
    {
        if (! $canViewFinancials) {
            return [];
        }

        return [
            'contract_amount' => $project->contract_amount,
            'estimated_cost' => $project->estimated_cost,
            'actual_cost' => $project->actual_cost,
            'gross_profit' => $project->gross_profit,
        ];
    }
}
