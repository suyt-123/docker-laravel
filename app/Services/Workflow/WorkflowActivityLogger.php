<?php

namespace App\Services\Workflow;

use App\Models\ProjectChangeOrder;
use App\Models\Quotation;
use App\Support\ActivityLogger;

class WorkflowActivityLogger
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function logQuotationStatusChange(
        string $action,
        string $event,
        Quotation $quotation,
        string $oldStatus,
        string $newStatus,
        string $description,
    ): void {
        $this->logger->log(
            $action,
            $event,
            $quotation,
            ['status' => $oldStatus],
            [
                'status' => $newStatus,
                'approved_by' => $quotation->approved_by,
            ],
            $description,
            'quotations',
        );
    }

    public function logProjectChangeOrderStatus(
        string $action,
        string $event,
        ProjectChangeOrder $order,
        string $description,
    ): void {
        $this->logger->log(
            $action,
            $event,
            $order,
            null,
            [
                'project_change_order_id' => $order->id,
                'project_id' => $order->project_id,
                'status' => $order->status,
            ],
            $description,
            'project_change_orders',
        );
    }
}
