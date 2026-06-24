<?php

namespace App\Http\Controllers\Api;

use App\Actions\Inventory\CreateInventoryTransaction;
use App\Actions\Inventory\DeleteInventoryTransaction;
use App\Actions\Inventory\UpdateInventoryTransaction;
use App\Auth\DataScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInventoryTransactionRequest;
use App\Http\Requests\UpdateInventoryTransactionRequest;
use App\Models\InventoryTransaction;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryTransactionController extends Controller
{
    public function __construct(
        private readonly DataScope $dataScope,
        private readonly CreateInventoryTransaction $createInventoryTransaction,
        private readonly UpdateInventoryTransaction $updateInventoryTransaction,
        private readonly DeleteInventoryTransaction $deleteInventoryTransaction,
    ) {}

    public function store(StoreInventoryTransactionRequest $request): JsonResponse
    {
        $this->ensureProjectVisible($request, $request->validated('project_id'));

        $transaction = $this->createInventoryTransaction->execute($request->validated(), $request->user());

        return $this->transactionResponse($transaction, '庫存異動已建立。', 201);
    }

    public function update(UpdateInventoryTransactionRequest $request, InventoryTransaction $inventoryTransaction): JsonResponse
    {
        $this->ensureVisible($request, $inventoryTransaction);
        $this->ensureProjectVisible($request, $request->validated('project_id'));

        $transaction = $this->updateInventoryTransaction->execute($inventoryTransaction, $request->validated());

        return $this->transactionResponse($transaction, '庫存異動已更新。');
    }

    public function destroy(Request $request, InventoryTransaction $inventoryTransaction): JsonResponse
    {
        $this->ensureVisible($request, $inventoryTransaction);

        $id = $inventoryTransaction->id;
        $this->deleteInventoryTransaction->execute($inventoryTransaction);

        return response()->json([
            'message' => '庫存異動已刪除。',
            'deleted_transaction_id' => $id,
        ]);
    }

    private function ensureVisible(Request $request, InventoryTransaction $inventoryTransaction): void
    {
        $this->ensureProjectVisible($request, $inventoryTransaction->project_id);
    }

    private function ensureProjectVisible(Request $request, mixed $projectId): void
    {
        if (! $projectId) {
            return;
        }

        $visible = $this->dataScope
            ->projects(Project::query(), $request->user())
            ->whereKey((int) $projectId)
            ->exists();

        abort_unless($visible, 403);
    }

    private function transactionResponse(InventoryTransaction $transaction, string $message, int $status = 200): JsonResponse
    {
        $transaction->load(['material:id,name,spec,unit,current_stock', 'project:id,project_no,name', 'creator:id,name']);

        return response()->json([
            'message' => $message,
            'transaction' => [
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
                'occurred_at' => $transaction->occurred_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            ],
        ], $status);
    }
}
