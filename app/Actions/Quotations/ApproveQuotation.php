<?php

namespace App\Actions\Quotations;

use App\Events\WorkflowNotificationRequested;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Workflow\WorkflowActivityLogger;
use Illuminate\Support\Facades\DB;

class ApproveQuotation
{
    public function __construct(private readonly WorkflowActivityLogger $logger) {}

    public function execute(Quotation $quotation, ?User $actor): Quotation
    {
        [$quotation, $oldStatus] = DB::transaction(function () use ($quotation, $actor) {
            $quotation = Quotation::query()
                ->lockForUpdate()
                ->findOrFail($quotation->id);

            abort_unless($quotation->status === 'reviewing', 422, '只有審核中的報價單可以核准。');

            $oldStatus = $quotation->status;
            $quotation->update([
                'status' => 'approved',
                'approved_by' => $actor?->id,
            ]);

            return [$quotation->refresh(), $oldStatus];
        });

        $this->logger->logQuotationStatusChange('approve', 'quotation.approved', $quotation, $oldStatus, 'approved', '報價單已核准');
        WorkflowNotificationRequested::dispatch(
            title: "報價單 {$quotation->quotation_no} 已核准",
            body: '報價單已核准，可送客戶確認。',
            capabilities: ['sales.quotations.send_customer.tenant'],
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
