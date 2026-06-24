<?php

namespace App\Actions\Purchasing;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class DeletePurchaseOrder
{
    public function execute(PurchaseOrder $purchaseOrder): int
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            abort_if($purchaseOrder->items()->where('received_quantity', '>', 0)->exists(), 422, '已有到貨驗收的採購單不可刪除。');

            $id = $purchaseOrder->id;
            $purchaseOrder->delete();

            return $id;
        });
    }
}
