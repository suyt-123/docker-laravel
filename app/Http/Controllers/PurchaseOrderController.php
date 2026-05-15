<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $orders = PurchaseOrder::query()
            ->with(['supplier:id,name,phone', 'creator:id,name'])
            ->withCount('items')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('purchase_order_no', 'ilike', "%{$search}%")
                    ->orWhereHas('supplier', fn ($query) => $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%"));
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order) => $this->orderPayload($order));

        return Inertia::render('PurchaseOrders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PurchaseOrders/Create', [
            'options' => $this->formOptions(),
            'statuses' => $this->statuses(),
            'purchaseOrderNo' => $this->nextPurchaseOrderNo(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $order = DB::transaction(function () use ($request) {
            $data = $this->orderData($request->validated());
            $data['purchase_order_no'] = filled($data['purchase_order_no'] ?? null)
                ? $data['purchase_order_no']
                : $this->nextPurchaseOrderNo();
            $data['created_by'] = $request->user()?->id;

            $order = PurchaseOrder::create($data);
            $this->syncItems($order, $request->validated('items'));

            return $order;
        });

        return redirect()
            ->route('purchase-orders.show', $order)
            ->with('success', '採購單已建立。');
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load([
            'supplier:id,name,contact_name,phone,email,payment_terms',
            'creator:id,name',
            'items.material:id,name,spec,unit,current_stock',
        ]);

        return Inertia::render('PurchaseOrders/Show', [
            'order' => [
                ...$this->orderPayload($purchaseOrder),
                'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item) => $this->itemPayload($item))->values(),
                'can_receive' => ! in_array($purchaseOrder->status, ['draft', 'completed', 'cancelled'], true),
            ],
            'statuses' => $this->statuses(),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): Response
    {
        abort_if(in_array($purchaseOrder->status, ['partially_received', 'completed', 'cancelled'], true), 422, '已驗收或取消的採購單不可編輯。');

        $purchaseOrder->load('items');

        return Inertia::render('PurchaseOrders/Edit', [
            'order' => [
                'id' => $purchaseOrder->id,
                'purchase_order_no' => $purchaseOrder->purchase_order_no,
                'supplier_id' => $purchaseOrder->supplier_id,
                'status' => $purchaseOrder->status,
                'ordered_date' => $purchaseOrder->ordered_date?->toDateString(),
                'expected_date' => $purchaseOrder->expected_date?->toDateString(),
                'tax' => $purchaseOrder->tax,
                'discount' => $purchaseOrder->discount,
                'note' => $purchaseOrder->note,
                'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item) => [
                    'material_id' => $item->material_id,
                    'name' => $item->name,
                    'spec' => $item->spec,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'note' => $item->note,
                ])->values(),
            ],
            'options' => $this->formOptions(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_if(in_array($purchaseOrder->status, ['partially_received', 'completed', 'cancelled'], true), 422, '已驗收或取消的採購單不可編輯。');

        DB::transaction(function () use ($request, $purchaseOrder) {
            $data = $this->orderData($request->validated());
            $data['purchase_order_no'] = $purchaseOrder->purchase_order_no;
            $purchaseOrder->update($data);
            $this->syncItems($purchaseOrder, $request->validated('items'));
        });

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', '採購單已更新。');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_if($purchaseOrder->items()->where('received_quantity', '>', 0)->exists(), 422, '已有到貨驗收的採購單不可刪除。');

        $purchaseOrder->delete();

        return redirect()
            ->route('purchase-orders.index')
            ->with('success', '採購單已刪除。');
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_if(in_array($purchaseOrder->status, ['draft', 'completed', 'cancelled'], true), 422, '此採購單目前不可驗收。');

        DB::transaction(function () use ($request, $purchaseOrder) {
            $purchaseOrder->load('items.material');
            $itemsById = $purchaseOrder->items->keyBy('id');
            $receivedAt = $request->validated('received_at') ?? now();
            $createdTransactions = [];

            foreach ($request->validated('items') as $receivedItem) {
                /** @var PurchaseOrderItem|null $item */
                $item = $itemsById->get((int) $receivedItem['id']);
                abort_unless($item, 422, '驗收項目不屬於此採購單。');

                $quantity = (float) $receivedItem['received_quantity'];
                if ($quantity <= 0) {
                    continue;
                }

                $remaining = (float) $item->quantity - (float) $item->received_quantity;
                abort_if($quantity > $remaining, 422, '到貨數量不可超過未到貨數量。');

                $transaction = InventoryTransaction::create([
                    'material_id' => $item->material_id,
                    'purchase_order_item_id' => $item->id,
                    'created_by' => $request->user()?->id,
                    'type' => 'purchase_in',
                    'quantity' => $quantity,
                    'unit' => $item->unit,
                    'unit_cost' => $item->unit_cost,
                    'total_cost' => (int) round($quantity * (int) $item->unit_cost),
                    'reference_no' => $purchaseOrder->purchase_order_no,
                    'note' => $receivedItem['note'] ?? null,
                    'occurred_at' => $receivedAt,
                ]);

                $item->material()->lockForUpdate()->firstOrFail()->increment('current_stock', $quantity);
                $item->increment('received_quantity', $quantity);
                $createdTransactions[] = $transaction->id;
            }

            abort_if($createdTransactions === [], 422, '請至少輸入一筆到貨數量。');

            $purchaseOrder->refresh()->load('items');
            $purchaseOrder->update([
                'status' => $this->receivedStatus($purchaseOrder),
            ]);

            app(ActivityLogger::class)->log(
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
        });

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', '到貨驗收已完成，庫存已入庫。');
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
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
    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
            'materials' => Material::query()
                ->orderBy('name')
                ->get(['id', 'name', 'spec', 'unit', 'cost_price', 'current_stock']),
        ];
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function orderData(array $data): array
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
    private function syncItems(PurchaseOrder $order, array $items): void
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

    private function receivedStatus(PurchaseOrder $order): string
    {
        $total = $order->items->sum(fn (PurchaseOrderItem $item) => (float) $item->quantity);
        $received = $order->items->sum(fn (PurchaseOrderItem $item) => (float) $item->received_quantity);

        return $received >= $total ? 'completed' : 'partially_received';
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(PurchaseOrder $order): array
    {
        return [
            'id' => $order->id,
            'purchase_order_no' => $order->purchase_order_no,
            'supplier' => $order->supplier,
            'creator' => $order->creator,
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
    private function itemPayload(PurchaseOrderItem $item): array
    {
        return [
            'id' => $item->id,
            'material' => $item->material,
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
}
