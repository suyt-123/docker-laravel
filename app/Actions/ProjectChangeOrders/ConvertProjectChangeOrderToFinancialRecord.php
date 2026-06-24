<?php

namespace App\Actions\ProjectChangeOrders;

use App\Models\FinancialRecord;
use App\Models\ProjectChangeOrder;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

class ConvertProjectChangeOrderToFinancialRecord
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @return array{0: ProjectChangeOrder, 1: FinancialRecord}
     */
    public function execute(ProjectChangeOrder $projectChangeOrder): array
    {
        [$order, $record] = DB::transaction(function () use ($projectChangeOrder) {
            $projectChangeOrder = ProjectChangeOrder::query()
                ->with('quotation')
                ->lockForUpdate()
                ->findOrFail($projectChangeOrder->id);

            abort_unless($projectChangeOrder->status === 'customer_confirmed', 422, '只有客戶已確認的追加單可以轉成追加款。');
            abort_if($projectChangeOrder->financial_record_id !== null, 422, '此追加單已轉成收款紀錄。');
            $this->ensureFormalQuotationReady($projectChangeOrder);

            $record = FinancialRecord::create([
                'project_id' => $projectChangeOrder->project_id,
                'project_change_order_id' => $projectChangeOrder->id,
                'type' => 'change_order',
                'title' => '追加款 - '.$projectChangeOrder->title,
                'amount' => $projectChangeOrder->amount,
                'due_date' => $projectChangeOrder->due_date,
                'status' => 'pending',
                'note' => $projectChangeOrder->customer_note ?: $projectChangeOrder->description,
            ]);

            $projectChangeOrder->update([
                'financial_record_id' => $record->id,
                'status' => 'converted',
                'converted_at' => now(),
            ]);

            return [$projectChangeOrder->refresh(), $record];
        });

        $this->logger->log(
            'convert',
            'project_change_order.converted_to_financial_record',
            $order,
            null,
            [
                'project_change_order_id' => $order->id,
                'project_id' => $order->project_id,
                'financial_record_id' => $record->id,
                'amount' => $record->amount,
            ],
            '工程變更追加單已轉成追加款收款紀錄',
            'project_change_orders',
        );

        return [$order, $record];
    }

    private function ensureFormalQuotationReady(ProjectChangeOrder $projectChangeOrder): void
    {
        if (! $projectChangeOrder->requires_formal_quotation) {
            return;
        }

        abort_unless($projectChangeOrder->quotation, 422, '此追加單需要正式報價，請先建立並核准追加報價單。');
        abort_unless($projectChangeOrder->quotation->status === 'approved', 422, '正式追加報價單尚未核准。');
    }
}
