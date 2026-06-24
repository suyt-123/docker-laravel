<?php

namespace App\Actions\Quotations;

use App\Events\WorkflowNotificationRequested;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Workflow\WorkflowActivityLogger;
use Illuminate\Support\Facades\DB;

class AcceptQuotationCustomer
{
    public function __construct(private readonly WorkflowActivityLogger $logger) {}

    /**
     * @param  array{customer_confirmed_by_name?: string|null}  $data
     */
    public function execute(Quotation $quotation, array $data, ?User $actor = null): Quotation
    {
        [$quotation, $oldStatus] = DB::transaction(function () use ($quotation, $data) {
            $quotation = Quotation::query()
                ->lockForUpdate()
                ->findOrFail($quotation->id);

            abort_unless(in_array($quotation->status, ['approved', 'sent'], true), 422, '只有已核准或已送出的報價單可以標記客戶接受。');

            $oldStatus = $quotation->status;
            $quotation->update([
                'status' => 'accepted',
                'customer_confirmation_status' => 'accepted',
                'customer_confirmed_at' => now(),
                'customer_confirmed_by_name' => $data['customer_confirmed_by_name'] ?? null,
                'locked_at' => now(),
            ]);

            return [$quotation->refresh(), $oldStatus];
        });

        $this->logger->logQuotationStatusChange('accept_customer', 'quotation.customer_accepted', $quotation, $oldStatus, 'accepted', '報價單已取得客戶接受');
        WorkflowNotificationRequested::dispatch(
            title: "報價單 {$quotation->quotation_no} 已被客戶接受",
            body: '報價單已鎖定，可評估轉工程案件。',
            capabilities: ['sales.quotations.convert_project.tenant'],
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
