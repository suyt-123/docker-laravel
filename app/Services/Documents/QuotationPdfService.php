<?php

namespace App\Services\Documents;

use App\Models\Quotation;
use App\Models\User;
use App\Services\SettingService;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class QuotationPdfService
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly PdfRenderer $renderer,
        private readonly DocumentVersionRecorder $versions,
        private readonly ActivityLogger $logger,
    ) {}

    public function render(Quotation $quotation, ?User $user): SymfonyResponse
    {
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
            return $this->renderer->htmlResponse($html, $quotation->quotation_no.'.html');
        }

        $directory = storage_path('app/pdf/quotations');
        File::ensureDirectoryExists($directory);

        $path = $this->cachePath($quotation, $directory, $settings, $statuses);

        if (File::exists($path)) {
            return $this->renderer->fileResponse($path, $quotation->quotation_no.'.pdf');
        }

        $this->renderer->renderA4($html, $path, $this->footerHtml());
        $this->pruneCachedPdfs($quotation, $path);

        $this->versions->record(
            $quotation,
            'quotation_pdf',
            $path,
            $quotation->quotation_no.'.pdf',
            [
                'total' => $quotation->total,
                'status' => $quotation->status,
                'customer_confirmation_status' => $quotation->customer_confirmation_status,
            ],
            $user,
        );

        $this->logger->log(
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

        return $this->renderer->fileResponse($path, $quotation->quotation_no.'.pdf');
    }

    public function pruneCachedPdfs(Quotation $quotation, ?string $currentPath = null): void
    {
        $directory = storage_path('app/pdf/quotations');

        foreach (File::glob($directory.DIRECTORY_SEPARATOR."quotation-{$quotation->id}-*.pdf") as $cachedPath) {
            if ($cachedPath !== $currentPath) {
                File::delete($cachedPath);
            }
        }
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

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $statuses
     */
    private function cachePath(Quotation $quotation, string $directory, array $settings, array $statuses): string
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

    private function footerHtml(): string
    {
        return <<<'HTML'
            <div style="width:100%; padding:0 12mm; color:#6b7280; font-family:'Noto Sans CJK TC','Noto Sans TC','DejaVu Sans',sans-serif; font-size:11px; text-align:center;">
                本報價單由系統產生，實際施工內容與付款條件以雙方確認版本為準。
                <span style="margin-left:8px;">第 <span class="pageNumber"></span> 頁 / 共 <span class="totalPages"></span> 頁</span>
            </div>
        HTML;
    }
}
