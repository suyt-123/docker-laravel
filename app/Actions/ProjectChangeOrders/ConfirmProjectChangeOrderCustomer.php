<?php

namespace App\Actions\ProjectChangeOrders;

use App\Events\WorkflowNotificationRequested;
use App\Models\ProjectChangeOrder;
use App\Models\User;
use App\Services\Workflow\WorkflowActivityLogger;
use Illuminate\Support\Facades\DB;

class ConfirmProjectChangeOrderCustomer
{
    public function __construct(private readonly WorkflowActivityLogger $logger) {}

    public function execute(ProjectChangeOrder $projectChangeOrder, ?User $actor = null): ProjectChangeOrder
    {
        $order = DB::transaction(function () use ($projectChangeOrder) {
            $projectChangeOrder = ProjectChangeOrder::query()
                ->with('quotation')
                ->lockForUpdate()
                ->findOrFail($projectChangeOrder->id);

            abort_unless($projectChangeOrder->status === 'approved', 422, '只有主管已核准的追加單可以標記客戶確認。');
            $this->ensureFormalQuotationReady($projectChangeOrder);

            $projectChangeOrder->update([
                'status' => 'customer_confirmed',
                'approved_date' => $projectChangeOrder->approved_date ?: now()->toDateString(),
                'customer_confirmed_at' => now(),
            ]);

            return $projectChangeOrder->refresh();
        });

        $this->logger->logProjectChangeOrderStatus(
            'confirm_customer',
            'project_change_order.customer_confirmed',
            $order,
            '工程變更追加單已客戶確認',
        );
        WorkflowNotificationRequested::dispatch(
            title: "追加單 {$order->title} 已取得客戶確認",
            body: '工程變更追加單可轉成追加款收款紀錄。',
            capabilities: ['projects.change_orders.convert_financial_record.tenant'],
            actionUrl: route('project-change-orders.show', $order),
            excludeUserId: $actor?->id,
            module: 'project_change_orders',
            payload: [
                'project_change_order_id' => $order->id,
                'title' => $order->title,
                'status' => $order->status,
            ],
        );

        return $order;
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
