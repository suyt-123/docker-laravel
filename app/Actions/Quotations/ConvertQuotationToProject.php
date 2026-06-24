<?php

namespace App\Actions\Quotations;

use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

class ConvertQuotationToProject
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function execute(Quotation $quotation, ?User $actor): Project
    {
        $project = DB::transaction(function () use ($quotation, $actor) {
            $quotation = Quotation::query()
                ->with(['customer:id,name', 'items'])
                ->lockForUpdate()
                ->findOrFail($quotation->id);

            abort_if($quotation->project_id, 422, '此報價單已綁定工程案件。');
            abort_unless($quotation->status === 'accepted', 422, '只有客戶已接受的報價單可以轉工程案件。');

            $estimatedCost = $quotation->items->sum(
                fn ($item) => (int) round((float) $item->quantity * (int) $item->cost_price),
            );

            $project = Project::create([
                'project_no' => $this->nextProjectNo(),
                'customer_id' => $quotation->customer_id,
                'manager_id' => $actor?->id,
                'name' => ($quotation->customer?->name ?: '未命名客戶').' - '.$quotation->quotation_no,
                'status' => 'contracted',
                'contract_amount' => $quotation->total,
                'estimated_cost' => $estimatedCost,
                'actual_cost' => 0,
                'gross_profit' => (int) $quotation->total - (int) $estimatedCost,
                'metadata' => [
                    'source_quotation_id' => $quotation->id,
                    'source_quotation_no' => $quotation->quotation_no,
                ],
            ]);

            $quotation->update(['project_id' => $project->id]);

            return $project;
        });

        $this->logger->log(
            'convert_project',
            'quotation.converted_to_project',
            $quotation,
            ['project_id' => null],
            [
                'project_id' => $project->id,
                'project_no' => $project->project_no,
            ],
            '報價單已轉工程案件',
            'quotations',
        );

        return $project;
    }

    private function nextProjectNo(): string
    {
        $year = now()->format('Y');
        $prefix = "TPH-{$year}-";
        $lastNo = Project::query()
            ->where('project_no', 'like', "{$prefix}%")
            ->orderByDesc('project_no')
            ->value('project_no');
        $next = $lastNo ? ((int) substr($lastNo, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
