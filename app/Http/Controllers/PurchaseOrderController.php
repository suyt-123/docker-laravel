<?php

namespace App\Http\Controllers;

use App\Actions\Purchasing\CreatePurchaseOrder;
use App\Actions\Purchasing\DeletePurchaseOrder;
use App\Actions\Purchasing\ReceivePurchaseOrder;
use App\Actions\Purchasing\UpdatePurchaseOrder;
use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Presenters\Purchasing\PurchaseOrderPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly CreatePurchaseOrder $createPurchaseOrder,
        private readonly UpdatePurchaseOrder $updatePurchaseOrder,
        private readonly DeletePurchaseOrder $deletePurchaseOrder,
        private readonly ReceivePurchaseOrder $receivePurchaseOrder,
        private readonly PurchaseOrderPresenter $purchaseOrderPresenter,
    ) {}

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
            ->through(fn (PurchaseOrder $order) => $this->purchaseOrderPresenter->indexItem($order));

        return Inertia::render('PurchaseOrders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => $this->purchaseOrderPresenter->statuses(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('PurchaseOrders/Create', [
            'options' => $this->purchaseOrderPresenter->formOptions(),
            'statuses' => $this->purchaseOrderPresenter->statuses(),
            'purchaseOrderNo' => $this->nextPurchaseOrderNo(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $order = $this->createPurchaseOrder->execute($request->validated(), $request->user());

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
            'order' => $this->purchaseOrderPresenter->show($purchaseOrder),
            'statuses' => $this->purchaseOrderPresenter->statuses(),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): Response
    {
        abort_if(in_array($purchaseOrder->status, ['partially_received', 'completed', 'cancelled'], true), 422, '已驗收或取消的採購單不可編輯。');

        $purchaseOrder->load('items');

        return Inertia::render('PurchaseOrders/Edit', [
            'order' => $this->purchaseOrderPresenter->form($purchaseOrder),
            'options' => $this->purchaseOrderPresenter->formOptions(),
            'statuses' => $this->purchaseOrderPresenter->statuses(),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $purchaseOrder = $this->updatePurchaseOrder->execute($purchaseOrder, $request->validated());

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', '採購單已更新。');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->deletePurchaseOrder->execute($purchaseOrder);

        return redirect()
            ->route('purchase-orders.index')
            ->with('success', '採購單已刪除。');
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->receivePurchaseOrder->execute($purchaseOrder, $request->validated(), $request->user());

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', '到貨驗收已完成，庫存已入庫。');
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
