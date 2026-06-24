<?php

namespace App\Actions\Inventory;

use App\Models\InventoryTransaction;
use App\Services\Inventory\InventoryTransactionService;

class DeleteInventoryTransaction
{
    public function __construct(private readonly InventoryTransactionService $transactions) {}

    public function execute(InventoryTransaction $transaction): void
    {
        $this->transactions->delete($transaction);
    }
}
