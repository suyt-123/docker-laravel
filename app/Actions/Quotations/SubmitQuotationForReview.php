<?php

namespace App\Actions\Quotations;

use App\Events\WorkflowNotificationRequested;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Workflow\WorkflowActivityLogger;
use Illuminate\Support\Facades\DB;

class SubmitQuotationForReview
{
    public function __construct(private readonly WorkflowActivityLogger $logger) {}

    public function execute(Quotation $quotation, ?User $actor = null): Quotation
    {
        [$quotation, $oldStatus] = DB::transaction(function () use ($quotation) {
            $quotation = Quotation::query()
                ->lockForUpdate()
                ->findOrFail($quotation->id);

            abort_unless($quotation->status === 'draft', 422, '只有草稿報價單可以送審。');

            $oldStatus = $quotation->status;
            $quotation->update([
                'status' => 'reviewing',
                'approved_by' => null,
            ]);

            return [$quotation->refresh(), $oldStatus];
        });

        $this->logger->logQuotationStatusChange('submit_review', 'quotation.submitted_for_review', $quotation, $oldStatus, 'reviewing', '報價單已送審');
        WorkflowNotificationRequested::dispatch(
            title: "報價單 {$quotation->quotation_no} 已送審",
            body: '有一張報價單等待核准。',
            capabilities: ['sales.quotations.approve.tenant'],
            actionUrl: route('quotations.show', $quotation),
            excludeUserId: $actor?->id,
            module: 'quotations',
            payload: [
                'quotation_id' => $quotation->id,
                'quotation_no' => $quotation->quotation_no,
                'status' => $quotation->status,
            ],
        );

        return $quotation;
    }
}
