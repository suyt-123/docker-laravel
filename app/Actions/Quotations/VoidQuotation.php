<?php

namespace App\Actions\Quotations;

use App\Models\Quotation;
use App\Services\Workflow\WorkflowActivityLogger;
use Illuminate\Support\Facades\DB;

class VoidQuotation
{
    public function __construct(private readonly WorkflowActivityLogger $logger) {}

    /**
     * @param  array{void_reason?: string|null}  $data
     */
    public function execute(Quotation $quotation, array $data): Quotation
    {
        [$quotation, $oldStatus] = DB::transaction(function () use ($quotation, $data) {
            $quotation = Quotation::query()
                ->lockForUpdate()
                ->findOrFail($quotation->id);

            abort_if($quotation->project_id, 422, '已轉工程案件的報價單不可作廢。');
            abort_if($quotation->status === 'voided', 422, '此報價單已作廢。');

            $oldStatus = $quotation->status;
            $quotation->update([
                'status' => 'voided',
                'voided_at' => now(),
                'void_reason' => $data['void_reason'] ?? null,
            ]);

            return [$quotation->refresh(), $oldStatus];
        });

        $this->logger->logQuotationStatusChange('void', 'quotation.voided', $quotation, $oldStatus, 'voided', '報價單已作廢');

        return $quotation;
    }
}
