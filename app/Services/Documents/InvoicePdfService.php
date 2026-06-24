<?php

namespace App\Services\Documents;

use App\Models\FinancialRecord;
use App\Models\Project;
use App\Models\User;
use App\Services\SettingService;
use App\Support\ActivityLogger;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InvoicePdfService
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly PdfRenderer $renderer,
        private readonly DocumentVersionRecorder $versions,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  Collection<int, int>  $ids
     */
    public function render(Project $project, Collection $ids, ?User $user): SymfonyResponse
    {
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
        $issuedAt = now();
        $html = view('pdf.invoice', [
            'project' => $project,
            'records' => $records,
            'types' => $this->financialRecordTypes(),
            'statuses' => $this->financialRecordStatuses(),
            'settings' => $this->settings->nested(),
            'total' => $total,
            'issuedAt' => $issuedAt,
        ])->render();

        if (config('documents.pdf_renderer') === 'html') {
            return $this->renderer->htmlResponse($html, $project->project_no.'-invoice.html');
        }

        $path = storage_path('app/pdf/invoices').DIRECTORY_SEPARATOR.$project->project_no.'-invoice-'.$issuedAt->format('YmdHis').'-'.uniqid().'.pdf';

        $this->renderer->renderA4($html, $path, $this->footerHtml());
        $this->versions->record(
            $project,
            'invoice_pdf',
            $path,
            $project->project_no.'-invoice.pdf',
            [
                'financial_record_ids' => $ids->all(),
                'total_amount' => $total,
                'issued_at' => $issuedAt->toDateString(),
            ],
            $user,
        );

        $this->logger->log(
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

        return $this->renderer->fileResponse($path, $project->project_no.'-invoice.pdf');
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

    private function footerHtml(): string
    {
        return <<<'HTML'
            <div style="width:100%; padding:0 12mm; color:#6b7280; font-family:'Noto Sans CJK TC','Noto Sans TC','DejaVu Sans',sans-serif; font-size:11px; text-align:center;">
                本請款單由系統產生，實際付款條件以雙方確認版本為準。
                <span style="margin-left:8px;">第 <span class="pageNumber"></span> 頁 / 共 <span class="totalPages"></span> 頁</span>
            </div>
        HTML;
    }
}
