<?php

namespace App\Actions\Purchasing;

use App\Events\WorkflowNotificationRequested;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\Inventory\InventoryTransactionService;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

class ReceivePurchaseOrder
{
    public function __construct(
        private readonly InventoryTransactionService $transactions,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{received_at?: mixed, items: array<int, array{id: mixed, received_quantity: mixed, note?: mixed}>}  $data
     */
    public function execute(PurchaseOrder $purchaseOrder, array $data, ?User $actor): void
    {
        $purchaseOrder = DB::transaction(function () use ($purchaseOrder, $data, $actor) {
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            abort_if(in_array($purchaseOrder->status, ['draft', 'completed', 'cancelled'], true), 422, '此採購單目前不可驗收。');

            $itemsById = $purchaseOrder
                ->items()
                ->with('material')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $receivedAt = $data['received_at'] ?? now();
            $createdTransactions = [];

            foreach ($data['items'] as $receivedItem) {
                /** @var PurchaseOrderItem|null $item */
                $item = $itemsById->get((int) $receivedItem['id']);
                abort_unless($item, 422, '驗收項目不屬於此採購單。');

                $quantity = (float) $receivedItem['received_quantity'];
                if ($quantity <= 0) {
                    continue;
                }

                $remaining = (float) $item->quantity - (float) $item->received_quantity;
                abort_if($quantity > $remaining, 422, '到貨數量不可超過未到貨數量。');

                $transaction = $this->transactions->createWithoutWrappingTransaction([
                    'material_id' => $item->material_id,
                    'purchase_order_item_id' => $item->id,
                    'type' => 'purchase_in',
                    'quantity' => $quantity,
                    'unit' => $item->unit,
                    'unit_cost' => $item->unit_cost,
                    'reference_no' => $purchaseOrder->purchase_order_no,
                    'note' => $receivedItem['note'] ?? null,
                    'occurred_at' => $receivedAt,
                ], $actor);

                $item->increment('received_quantity', $quantity);
                $createdTransactions[] = $transaction->id;
            }

            abort_if($createdTransactions === [], 422, '請至少輸入一筆到貨數量。');

            $purchaseOrder->refresh()->load('items');
            $purchaseOrder->update([
                'status' => $this->receivedStatus($purchaseOrder),
            ]);

            $this->logger->log(
                'receive',
                'purchase_order.received',
                $purchaseOrder,
                null,
                [
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_no' => $purchaseOrder->purchase_order_no,
                    'inventory_transaction_ids' => $createdTransactions,
                ],
                '採購單已到貨驗收入庫',
                'purchase_orders',
            );

            return $purchaseOrder->refresh();
        });

        WorkflowNotificationRequested::dispatch(
            title: "採購單 {$purchaseOrder->purchase_order_no} 已到貨驗收",
            body: '採購單已完成到貨驗收，相關材料庫存已更新。',
            capabilities: ['purchasing.purchase_orders.view.tenant'],
            actionUrl: route('purchase-orders.show', $purchaseOrder),
            excludeUserId: $actor?->id,
            module: 'purchase_orders',
            payload: [
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_no' => $purchaseOrder->purchase_order_no,
                'status' => $purchaseOrder->status,
            ],
        );
    }

    private function receivedStatus(PurchaseOrder $order): string
    {
        $total = $order->items->sum(fn (PurchaseOrderItem $item) => (float) $item->quantity);
        $received = $order->items->sum(fn (PurchaseOrderItem $item) => (float) $item->received_quantity);

        return $received >= $total ? 'completed' : 'partially_received';
    }
}
