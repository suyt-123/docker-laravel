<?php

namespace App\Actions\ProjectChangeOrders;

use App\Events\WorkflowNotificationRequested;
use App\Models\ProjectChangeOrder;
use App\Models\User;
use App\Services\Workflow\WorkflowActivityLogger;
use Illuminate\Support\Facades\DB;

class SubmitProjectChangeOrderForReview
{
    public function __construct(private readonly WorkflowActivityLogger $logger) {}

    public function execute(ProjectChangeOrder $projectChangeOrder, ?User $actor = null): ProjectChangeOrder
    {
        $order = DB::transaction(function () use ($projectChangeOrder) {
            $projectChangeOrder = ProjectChangeOrder::query()
                ->lockForUpdate()
                ->findOrFail($projectChangeOrder->id);

            abort_unless($projectChangeOrder->status === 'draft', 422, '只有草稿追加單可以送審。');

            $projectChangeOrder->update([
                'status' => 'pending_approval',
                'submitted_at' => now(),
            ]);

            return $projectChangeOrder->refresh();
        });

        $this->logger->logProjectChangeOrderStatus(
            'submit_review',
            'project_change_order.submitted_for_review',
            $order,
            '工程變更追加單已送主管核准',
        );
        WorkflowNotificationRequested::dispatch(
            title: "追加單 {$order->title} 已送審",
            body: '有一張工程變更追加單等待主管核准。',
            capabilities: ['projects.change_orders.approve.tenant'],
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
}
