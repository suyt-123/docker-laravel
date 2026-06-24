<?php

namespace App\Presenters\Purchasing;

use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Presenters\Concerns\PresentsModelSummaries;

class PurchaseOrderPresenter
{
    use PresentsModelSummaries;

    /**
     * @return array<string, string>
     */
    public function statuses(): array
    {
        return [
            'draft' => '草稿',
            'sent' => '已送出',
            'partially_received' => '部分到貨',
            'completed' => '已完成',
            'cancelled' => '已取消',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function indexItem(PurchaseOrder $order): array
    {
        return [
            'id' => $order->id,
            'purchase_order_no' => $order->purchase_order_no,
            'supplier' => $this->supplierSummary($order->supplier, ['id', 'name', 'phone']),
            'creator' => $this->userSummary($order->creator),
            'status' => $order->status,
            'ordered_date' => $order->ordered_date?->toDateString(),
            'expected_date' => $order->expected_date?->toDateString(),
            'subtotal' => $order->subtotal,
            'tax' => $order->tax,
            'discount' => $order->discount,
            'total' => $order->total,
            'note' => $order->note,
            'items_count' => $order->items_count ?? $order->items->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(PurchaseOrder $order): array
    {
        return [
            ...$this->indexItem($order),
            'supplier' => $this->supplierSummary($order->supplier, ['id', 'name', 'contact_name', 'phone', 'email', 'payment_terms']),
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => $this->item($item))->values(),
            'can_receive' => ! in_array($order->status, ['draft', 'completed', 'cancelled'], true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function form(PurchaseOrder $order): array
    {
        return [
            'id' => $order->id,
            'purchase_order_no' => $order->purchase_order_no,
            'supplier_id' => $order->supplier_id,
            'status' => $order->status,
            'ordered_date' => $order->ordered_date?->toDateString(),
            'expected_date' => $order->expected_date?->toDateString(),
            'tax' => $order->tax,
            'discount' => $order->discount,
            'note' => $order->note,
            'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                'material_id' => $item->material_id,
                'name' => $item->name,
                'spec' => $item->spec,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'note' => $item->note,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formOptions(): array
    {
        return [
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone'])
                ->map(fn (Supplier $supplier) => $this->supplierSummary($supplier, ['id', 'name', 'phone'])),
            'materials' => Material::query()
                ->orderBy('name')
                ->get(['id', 'name', 'spec', 'unit', 'cost_price', 'current_stock'])
                ->map(fn (Material $material) => $this->materialSummary($material, ['id', 'name', 'spec', 'unit', 'cost_price', 'current_stock'])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function item(PurchaseOrderItem $item): array
    {
        return [
            'id' => $item->id,
            'material' => $this->materialSummary($item->material, ['id', 'name', 'spec', 'unit', 'current_stock']),
            'material_id' => $item->material_id,
            'name' => $item->name,
            'spec' => $item->spec,
            'unit' => $item->unit,
            'quantity' => $item->quantity,
            'received_quantity' => $item->received_quantity,
            'remaining_quantity' => max(0, (float) $item->quantity - (float) $item->received_quantity),
            'unit_cost' => $item->unit_cost,
            'subtotal' => $item->subtotal,
            'note' => $item->note,
        ];
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>|null
     */
    private function supplierSummary(?Supplier $supplier, array $keys = ['id', 'name']): ?array
    {
        return $this->modelOnly($supplier, $keys);
    }
}
