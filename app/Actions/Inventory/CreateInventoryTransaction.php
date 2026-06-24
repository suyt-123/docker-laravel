<?php

namespace App\Actions\Inventory;

use App\Models\InventoryTransaction;
use App\Models\User;
use App\Services\Inventory\InventoryTransactionService;

class CreateInventoryTransaction
{
    public function __construct(private readonly InventoryTransactionService $transactions) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?User $actor): InventoryTransaction
    {
        return $this->transactions->create($data, $actor);
    }
}
