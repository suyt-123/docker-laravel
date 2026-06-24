<?php

namespace App\Services\Inventory;

use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryTransactionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor): InventoryTransaction
    {
        return DB::transaction(fn () => $this->createWithoutWrappingTransaction($data, $actor));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createWithoutWrappingTransaction(array $data, ?User $actor): InventoryTransaction
    {
        $transaction = InventoryTransaction::create([
            ...$this->transactionData($data),
            'created_by' => $actor?->id,
        ]);

        $this->applyStockDelta($transaction);

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(InventoryTransaction $transaction, array $data): InventoryTransaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $this->revertStockDelta($transaction);
            $transaction->update($this->transactionData($data));
            $this->applyStockDelta($transaction->refresh());

            return $transaction;
        });
    }

    public function delete(InventoryTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $this->revertStockDelta($transaction);
            $transaction->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function transactionData(array $data): array
    {
        $quantity = (float) $data['quantity'];
        $unitCost = (int) ($data['unit_cost'] ?? 0);

        return [
            ...$data,
            'unit_cost' => $unitCost,
            'total_cost' => (int) round($quantity * $unitCost),
            'occurred_at' => $data['occurred_at'] ?? now(),
        ];
    }

    private function applyStockDelta(InventoryTransaction $transaction): void
    {
        $transaction->material()->lockForUpdate()->firstOrFail()->increment(
            'current_stock',
            $this->stockDelta($transaction),
        );
    }

    private function revertStockDelta(InventoryTransaction $transaction): void
    {
        $transaction->material()->lockForUpdate()->firstOrFail()->decrement(
            'current_stock',
            $this->stockDelta($transaction),
        );
    }

    private function stockDelta(InventoryTransaction $transaction): float
    {
        $quantity = (float) $transaction->quantity;

        return match ($transaction->type) {
            'inbound', 'purchase_in', 'return' => $quantity,
            'outbound', 'transfer', 'waste' => -$quantity,
            'adjustment' => $quantity,
            default => 0,
        };
    }
}
