<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\CreateInventoryTransaction;
use App\Actions\Inventory\DeleteInventoryTransaction;
use App\Actions\Inventory\UpdateInventoryTransaction;
use App\Auth\DataScope;
use App\Http\Requests\StoreInventoryTransactionRequest;
use App\Http\Requests\UpdateInventoryTransactionRequest;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryTransactionController extends Controller
{
    public function __construct(
        private readonly DataScope $dataScope,
        private readonly CreateInventoryTransaction $createInventoryTransaction,
        private readonly UpdateInventoryTransaction $updateInventoryTransaction,
        private readonly DeleteInventoryTransaction $deleteInventoryTransaction,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', ''));

        $transactions = InventoryTransaction::query()
            ->with(['material:id,name,spec,unit,current_stock', 'project:id,project_no,name', 'creator:id,name'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('reference_no', 'ilike', "%{$search}%")
                    ->orWhereHas('material', fn ($query) => $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('spec', 'ilike', "%{$search}%"))
                    ->orWhereHas('project', fn ($query) => $query
                        ->where('project_no', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%"));
            }))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->latest('occurred_at')
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (InventoryTransaction $transaction) => [
                'id' => $transaction->id,
                'material' => $transaction->material,
                'project' => $transaction->project,
                'creator' => $transaction->creator,
                'type' => $transaction->type,
                'quantity' => $transaction->quantity,
                'unit' => $transaction->unit,
                'unit_cost' => $transaction->unit_cost,
                'total_cost' => $transaction->total_cost,
                'reference_no' => $transaction->reference_no,
                'note' => $transaction->note,
                'occurred_at' => $transaction->occurred_at?->format('Y-m-d\TH:i'),
            ]);

        return Inertia::render('InventoryTransactions/Index', [
            'transactions' => $transactions,
            'filters' => [
                'search' => $search,
                'type' => $type,
            ],
            'types' => $this->types(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('InventoryTransactions/Create', [
            'options' => $this->formOptions(),
            'types' => $this->types(),
        ]);
    }

    public function store(StoreInventoryTransactionRequest $request): RedirectResponse
    {
        $transaction = $this->createInventoryTransaction->execute($request->validated(), $request->user());

        return redirect()
            ->route('inventory-transactions.show', $transaction)
            ->with('success', '庫存異動已建立。');
    }

    public function show(Request $request, InventoryTransaction $inventoryTransaction): Response
    {
        $this->ensureVisible($request, $inventoryTransaction);

        $inventoryTransaction->load(['material:id,name,spec,unit,current_stock', 'project:id,project_no,name', 'creator:id,name']);

        return Inertia::render('InventoryTransactions/Show', [
            'transaction' => [
                'id' => $inventoryTransaction->id,
                'material' => $inventoryTransaction->material,
                'project' => $inventoryTransaction->project,
                'creator' => $inventoryTransaction->creator,
                'type' => $inventoryTransaction->type,
                'quantity' => $inventoryTransaction->quantity,
                'unit' => $inventoryTransaction->unit,
                'unit_cost' => $inventoryTransaction->unit_cost,
                'total_cost' => $inventoryTransaction->total_cost,
                'reference_no' => $inventoryTransaction->reference_no,
                'note' => $inventoryTransaction->note,
                'occurred_at' => $inventoryTransaction->occurred_at?->format('Y-m-d H:i'),
            ],
            'types' => $this->types(),
        ]);
    }

    public function edit(Request $request, InventoryTransaction $inventoryTransaction): Response
    {
        $this->ensureVisible($request, $inventoryTransaction);

        return Inertia::render('InventoryTransactions/Edit', [
            'transaction' => [
                'id' => $inventoryTransaction->id,
                'material_id' => $inventoryTransaction->material_id,
                'project_id' => $inventoryTransaction->project_id,
                'type' => $inventoryTransaction->type,
                'quantity' => $inventoryTransaction->quantity,
                'unit' => $inventoryTransaction->unit,
                'unit_cost' => $inventoryTransaction->unit_cost,
                'reference_no' => $inventoryTransaction->reference_no,
                'note' => $inventoryTransaction->note,
                'occurred_at' => $inventoryTransaction->occurred_at?->format('Y-m-d\TH:i'),
            ],
            'options' => $this->formOptions(),
            'types' => $this->types(),
        ]);
    }

    public function update(UpdateInventoryTransactionRequest $request, InventoryTransaction $inventoryTransaction): RedirectResponse
    {
        $this->ensureVisible($request, $inventoryTransaction);

        $this->updateInventoryTransaction->execute($inventoryTransaction, $request->validated());

        return redirect()
            ->route('inventory-transactions.show', $inventoryTransaction)
            ->with('success', '庫存異動已更新。');
    }

    public function destroy(Request $request, InventoryTransaction $inventoryTransaction): RedirectResponse
    {
        $this->ensureVisible($request, $inventoryTransaction);

        $this->deleteInventoryTransaction->execute($inventoryTransaction);

        return redirect()
            ->route('inventory-transactions.index')
            ->with('success', '庫存異動已刪除。');
    }

    /**
     * @return array<string, string>
     */
    private function types(): array
    {
        return [
            'inbound' => '入庫',
            'purchase_in' => '採購入庫',
            'outbound' => '出庫',
            'return' => '退料',
            'transfer' => '工地調撥',
            'adjustment' => '盤點調整',
            'waste' => '損耗',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'materials' => Material::query()
                ->orderBy('name')
                ->get(['id', 'name', 'spec', 'unit', 'cost_price', 'current_stock']),
            'projects' => Project::query()
                ->orderByDesc('id')
                ->get(['id', 'project_no', 'name']),
        ];
    }

    private function ensureVisible(Request $request, InventoryTransaction $inventoryTransaction): void
    {
        if (! $inventoryTransaction->project_id) {
            return;
        }

        $visible = $this->dataScope
            ->projects(Project::query(), $request->user())
            ->whereKey($inventoryTransaction->project_id)
            ->exists();

        abort_unless($visible, 403);
    }
}
