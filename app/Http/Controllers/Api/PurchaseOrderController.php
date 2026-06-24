<?php

namespace App\Http\Controllers\Api;

use App\Actions\Purchasing\CreatePurchaseOrder;
use App\Actions\Purchasing\DeletePurchaseOrder;
use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Actions\Purchasing\UpdatePurchaseOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Presenters\Purchasing\PurchaseOrderPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly CreatePurchaseOrder $createPurchaseOrder,
        private readonly UpdatePurchaseOrder $updatePurchaseOrder,
        private readonly DeletePurchaseOrder $deletePurchaseOrder,
        private readonly ReceivePurchaseOrder $receivePurchaseOrder,
        private readonly PurchaseOrderPresenter $purchaseOrderPresenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $perPage = min(max((int) $request->query('per_page', 12), 1), 100);

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
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order) => $this->purchaseOrderPresenter->indexItem($order));

        return response()->json([
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => $this->purchaseOrderPresenter->statuses(),
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load([
            'supplier:id,name,contact_name,phone,email,payment_terms',
            'creator:id,name',
            'items.material:id,name,spec,unit,current_stock',
        ]);

        return response()->json([
            'order' => $this->purchaseOrderPresenter->show($purchaseOrder),
            'statuses' => $this->purchaseOrderPresenter->statuses(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $purchaseOrder = $this->createPurchaseOrder->execute($request->validated(), $request->user());

        return $this->orderResponse($purchaseOrder, '採購單已建立。', 201);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder = $this->updatePurchaseOrder->execute($purchaseOrder, $request->validated());

        return $this->orderResponse($purchaseOrder, '採購單已更新。');
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $id = $this->deletePurchaseOrder->execute($purchaseOrder);

        return response()->json([
            'message' => '採購單已刪除。',
            'deleted_purchase_order_id' => $id,
        ]);
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->receivePurchaseOrder->execute($purchaseOrder, $request->validated(), $request->user());

        return $this->orderResponse($purchaseOrder->refresh(), '到貨驗收已完成，庫存已入庫。');
    }

    private function orderResponse(PurchaseOrder $purchaseOrder, string $message, int $status = 200): JsonResponse
    {
        $purchaseOrder->load([
            'supplier:id,name,contact_name,phone,email,payment_terms',
            'creator:id,name',
            'items.material:id,name,spec,unit,current_stock',
        ]);

        return response()->json([
            'message' => $message,
            'order' => $this->purchaseOrderPresenter->show($purchaseOrder),
            'statuses' => $this->purchaseOrderPresenter->statuses(),
        ], $status);
    }
}
