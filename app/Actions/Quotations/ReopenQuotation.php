<?php

namespace App\Actions\Quotations;

use App\Models\Quotation;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

class ReopenQuotation
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function execute(Quotation $quotation, ?User $actor): Quotation
    {
        $newQuotation = DB::transaction(function () use ($quotation, $actor) {
            $quotation = Quotation::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($quotation->id);

            abort_if($quotation->project_id, 422, '已轉工程案件的報價單不可重開版本。');
            abort_unless(in_array($quotation->status, ['approved', 'sent', 'accepted', 'rejected', 'voided'], true), 422, '目前狀態不可重開版本。');

            $newQuotation = $quotation->replicate([
                'quotation_no',
                'status',
                'approved_by',
                'customer_confirmation_status',
                'customer_sent_at',
                'customer_confirmed_at',
                'customer_confirmed_by_name',
                'locked_at',
                'voided_at',
                'void_reason',
                'superseded_by_id',
                'created_at',
                'updated_at',
            ]);

            $newQuotation->forceFill([
                'quotation_no' => $this->nextQuotationNo(),
                'status' => 'draft',
                'approved_by' => null,
                'created_by' => $actor?->id,
                'customer_confirmation_status' => 'not_sent',
                'reopened_from_id' => $quotation->id,
            ])->save();

            foreach ($quotation->items as $item) {
                $newQuotation->items()->create($item->only([
                    'material_id',
                    'name',
                    'spec',
                    'unit',
                    'quantity',
                    'unit_price',
                    'cost_price',
                    'waste_rate',
                    'subtotal',
                    'note',
                ]));
            }

            $quotation->update(['superseded_by_id' => $newQuotation->id]);

            return $newQuotation;
        });

        $this->logger->log(
            'reopen',
            'quotation.reopened',
            $quotation,
            null,
            [
                'new_quotation_id' => $newQuotation->id,
                'new_quotation_no' => $newQuotation->quotation_no,
            ],
            '報價單已重開新版本',
            'quotations',
        );

        return $newQuotation;
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
