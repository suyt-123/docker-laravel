<?php

namespace App\Actions\Purchasing;

use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderItemSyncService;
use Illuminate\Support\Facades\DB;

class UpdatePurchaseOrder
{
    public function __construct(private readonly PurchaseOrderItemSyncService $items) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $data) {
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            abort_if(in_array($purchaseOrder->status, ['partially_received', 'completed', 'cancelled'], true), 422, '已驗收或取消的採購單不可編輯。');

            $orderData = $this->items->orderData($data);
            $orderData['purchase_order_no'] = $purchaseOrder->purchase_order_no;

            $purchaseOrder->update($orderData);
            $this->items->syncItems($purchaseOrder, $data['items']);

            return $purchaseOrder->refresh();
        });
    }
}
