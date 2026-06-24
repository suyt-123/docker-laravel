<?php

namespace App\Actions\ProjectChangeOrders;

use App\Models\ProjectChangeOrder;
use App\Models\Quotation;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

class CreateProjectChangeOrderQuotation
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function execute(ProjectChangeOrder $projectChangeOrder, ?User $actor): Quotation
    {
        $quotation = DB::transaction(function () use ($projectChangeOrder, $actor) {
            $projectChangeOrder = ProjectChangeOrder::query()
                ->with('project.customer')
                ->lockForUpdate()
                ->findOrFail($projectChangeOrder->id);

            abort_if($projectChangeOrder->quotation_id !== null, 422, '此追加單已建立追加報價單。');
            abort_unless($projectChangeOrder->requires_formal_quotation, 422, '此追加單未設定需要正式報價。');
            abort_unless(in_array($projectChangeOrder->status, ['draft', 'pending_approval', 'approved'], true), 422, '此追加單目前不可建立追加報價單。');

            $quotation = Quotation::create([
                'quotation_no' => $this->nextQuotationNo(),
                'customer_id' => $projectChangeOrder->project->customer_id,
                'project_id' => $projectChangeOrder->project_id,
                'created_by' => $actor?->id,
                'status' => 'draft',
                'subtotal' => $projectChangeOrder->amount,
                'tax' => 0,
                'discount' => 0,
                'total' => $projectChangeOrder->amount,
                'note' => "來源追加單：{$projectChangeOrder->title}",
                'items_json' => [
                    'source_project_change_order_id' => $projectChangeOrder->id,
                ],
            ]);

            $quotation->items()->create([
                'material_id' => null,
                'name' => $projectChangeOrder->title,
                'spec' => '工程變更追加',
                'unit' => '式',
                'quantity' => 1,
                'unit_price' => $projectChangeOrder->amount,
                'cost_price' => 0,
                'waste_rate' => 0,
                'subtotal' => $projectChangeOrder->amount,
                'note' => $projectChangeOrder->description,
            ]);

            $projectChangeOrder->update(['quotation_id' => $quotation->id]);

            return $quotation;
        });

        $this->logger->log(
            'create_quotation',
            'project_change_order.quotation_created',
            $projectChangeOrder->refresh(),
            null,
            [
                'project_change_order_id' => $projectChangeOrder->id,
                'quotation_id' => $quotation->id,
                'quotation_no' => $quotation->quotation_no,
            ],
            '工程變更追加單已建立追加報價單',
            'project_change_orders',
        );

        return $quotation;
    }

    private function nextQuotationNo(): string
    {
        $year = now()->format('Y');
        $prefix = "Q-{$year}-";
        $lastNo = Quotation::query()
            ->where('quotation_no', 'like', "{$prefix}%")
            ->orderByDesc('quotation_no')
            ->value('quotation_no');
        $next = $lastNo ? ((int) substr($lastNo, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
