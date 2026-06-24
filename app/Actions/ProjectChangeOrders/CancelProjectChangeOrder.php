<?php

namespace App\Actions\ProjectChangeOrders;

use App\Models\ProjectChangeOrder;
use App\Services\Workflow\WorkflowActivityLogger;
use Illuminate\Support\Facades\DB;

class CancelProjectChangeOrder
{
    public function __construct(private readonly WorkflowActivityLogger $logger) {}

    public function execute(ProjectChangeOrder $projectChangeOrder): ProjectChangeOrder
    {
        $order = DB::transaction(function () use ($projectChangeOrder) {
            $projectChangeOrder = ProjectChangeOrder::query()
                ->lockForUpdate()
                ->findOrFail($projectChangeOrder->id);

            abort_if($projectChangeOrder->status === 'converted', 422, '已轉追加款的追加單不可取消。');

            $projectChangeOrder->update(['status' => 'cancelled']);

            return $projectChangeOrder->refresh();
        });

        $this->logger->logProjectChangeOrderStatus(
            'cancel',
            'project_change_order.cancelled',
            $order,
            '工程變更追加單已取消',
        );

        return $order;
    }
}
