<?php

namespace App\Actions\Quotations;

use App\Models\Quotation;
use App\Services\Workflow\WorkflowActivityLogger;
use Illuminate\Support\Facades\DB;

class RejectQuotation
{
    public function __construct(private readonly WorkflowActivityLogger $logger) {}

    public function execute(Quotation $quotation): Quotation
    {
        [$quotation, $oldStatus] = DB::transaction(function () use ($quotation) {
            $quotation = Quotation::query()
                ->lockForUpdate()
                ->findOrFail($quotation->id);

            abort_unless($quotation->status === 'reviewing', 422, '只有審核中的報價單可以退回。');

            $oldStatus = $quotation->status;
            $quotation->update([
                'status' => 'draft',
                'approved_by' => null,
            ]);

            return [$quotation->refresh(), $oldStatus];
        });

        $this->logger->logQuotationStatusChange('reject', 'quotation.rejected', $quotation, $oldStatus, 'draft', '報價單已退回草稿');

        return $quotation;
    }
}
