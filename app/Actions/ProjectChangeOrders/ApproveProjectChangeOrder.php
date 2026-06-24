<?php

namespace App\Actions\ProjectChangeOrders;

use App\Events\WorkflowNotificationRequested;
use App\Models\ProjectChangeOrder;
use App\Models\User;
use App\Services\Workflow\WorkflowActivityLogger;
use Illuminate\Support\Facades\DB;

class ApproveProjectChangeOrder
{
    public function __construct(private readonly WorkflowActivityLogger $logger) {}

    public function execute(ProjectChangeOrder $projectChangeOrder, User $actor): ProjectChangeOrder
    {
        $order = DB::transaction(function () use ($projectChangeOrder, $actor) {
            $projectChangeOrder = ProjectChangeOrder::query()
                ->lockForUpdate()
                ->findOrFail($projectChangeOrder->id);

            abort_unless($projectChangeOrder->status === 'pending_approval', 422, '只有待主管核准的追加單可以核准。');

            $projectChangeOrder->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            return $projectChangeOrder->refresh();
        });

        $this->logger->logProjectChangeOrderStatus(
            'approve',
            'project_change_order.approved',
            $order,
            '工程變更追加單已主管核准',
        );
        WorkflowNotificationRequested::dispatch(
            title: "追加單 {$order->title} 已核准",
            body: '工程變更追加單已核准，可進行客戶確認。',
            capabilities: ['projects.change_orders.confirm_customer.tenant'],
            actionUrl: route('project-change-orders.show', $order),
            excludeUserId: $actor->id,
            module: 'project_change_orders',
            payload: [
                'project_change_order_id' => $order->id,
                'title' => $order->title,
                'status' => $order->status,
            ],
        );

        return $order;
    }
}
