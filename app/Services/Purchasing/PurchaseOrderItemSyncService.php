<?php

namespace App\Services\Purchasing;

use App\Models\PurchaseOrder;

class PurchaseOrderItemSyncService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function orderData(array $data): array
    {
        $items = collect($data['items']);
        $subtotal = $items->sum(fn ($item) => (int) round((float) $item['quantity'] * (int) $item['unit_cost']));
        $tax = (int) ($data['tax'] ?? 0);
        $discount = (int) ($data['discount'] ?? 0);

        unset($data['items']);

        return [
            ...$data,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => max(0, $subtotal + $tax - $discount),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function syncItems(PurchaseOrder $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $unitCost = (int) $item['unit_cost'];

            $order->items()->create([
                'material_id' => $item['material_id'],
                'name' => $item['name'],
                'spec' => $item['spec'] ?? null,
                'unit' => $item['unit'],
                'quantity' => $quantity,
                'received_quantity' => 0,
                'unit_cost' => $unitCost,
                'subtotal' => (int) round($quantity * $unitCost),
                'note' => $item['note'] ?? null,
            ]);
        }
    }
}
