<?php

namespace App\Actions\Inventory;

use App\Models\InventoryTransaction;
use App\Services\Inventory\InventoryTransactionService;

class UpdateInventoryTransaction
{
    public function __construct(private readonly InventoryTransactionService $transactions) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(InventoryTransaction $transaction, array $data): InventoryTransaction
    {
        return $this->transactions->update($transaction, $data);
    }
}
