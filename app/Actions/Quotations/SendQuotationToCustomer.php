<?php

namespace App\Actions\Quotations;

use App\Models\Quotation;
use App\Services\Workflow\WorkflowActivityLogger;
use Illuminate\Support\Facades\DB;

class SendQuotationToCustomer
{
    public function __construct(private readonly WorkflowActivityLogger $logger) {}

    public function execute(Quotation $quotation): Quotation
    {
        [$quotation, $oldStatus] = DB::transaction(function () use ($quotation) {
            $quotation = Quotation::query()
                ->lockForUpdate()
                ->findOrFail($quotation->id);

            abort_unless($quotation->status === 'approved', 422, '只有已核准報價單可以送客戶確認。');

            $oldStatus = $quotation->status;
            $quotation->update([
                'status' => 'sent',
                'customer_confirmation_status' => 'pending',
                'customer_sent_at' => now(),
            ]);

            return [$quotation->refresh(), $oldStatus];
        });

        $this->logger->logQuotationStatusChange('send_customer', 'quotation.sent_to_customer', $quotation, $oldStatus, 'sent', '報價單已送客戶確認');

        return $quotation;
    }
}
