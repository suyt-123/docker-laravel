<?php

namespace App\Actions\Purchasing;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderItemSyncService;
use Illuminate\Support\Facades\DB;

class CreatePurchaseOrder
{
    public function __construct(private readonly PurchaseOrderItemSyncService $items) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?User $actor = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $actor) {
            $orderData = $this->items->orderData($data);
            $orderData['purchase_order_no'] = filled($orderData['purchase_order_no'] ?? null)
                ? $orderData['purchase_order_no']
                : $this->nextPurchaseOrderNo();
            $orderData['created_by'] = $actor?->id;

            $order = PurchaseOrder::create($orderData);
            $this->items->syncItems($order, $data['items']);

            return $order->refresh();
        });
    }

    private function nextPurchaseOrderNo(): string
    {
        $year = now()->format('Y');
        $prefix = "PO-{$year}-";
        $lastNo = PurchaseOrder::query()
            ->where('purchase_order_no', 'like', "{$prefix}%")
            ->orderByDesc('purchase_order_no')
            ->value('purchase_order_no');
        $next = $lastNo ? ((int) substr($lastNo, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
