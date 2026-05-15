<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTransactionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_inventory_transaction_pages(): void
    {
        $this->get(route('inventory-transactions.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_inventory_transaction_index(): void
    {
        $user = $this->authorizedUser();
        $material = Material::create([
            'name' => 'C 型鋼',
            'unit' => '支',
            'current_stock' => 20,
        ]);

        InventoryTransaction::create([
            'material_id' => $material->id,
            'created_by' => $user->id,
            'type' => 'inbound',
            'quantity' => 5,
            'unit' => '支',
            'unit_cost' => 800,
            'total_cost' => 4000,
            'occurred_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('inventory-transactions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('InventoryTransactions/Index')
                ->has('transactions.data', 1)
            );
    }

    public function test_authenticated_user_can_create_inbound_transaction_and_update_stock(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '庫存客戶']);
        $project = Project::create([
            'project_no' => 'TPH-2026-0001',
            'customer_id' => $customer->id,
            'name' => '庫存案件',
            'status' => 'preparing',
        ]);
        $material = Material::create([
            'name' => 'C 型鋼',
            'unit' => '支',
            'cost_price' => 800,
            'current_stock' => 10,
        ]);

        $this
            ->actingAs($user)
            ->post(route('inventory-transactions.store'), [
                'material_id' => $material->id,
                'project_id' => $project->id,
                'type' => 'inbound',
                'quantity' => 5,
                'unit' => '支',
                'unit_cost' => 800,
                'reference_no' => 'PO-001',
                'occurred_at' => '2026-06-01 09:00:00',
            ])
            ->assertRedirect();

        $material->refresh();

        $this->assertSame('15.000', $material->current_stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'material_id' => $material->id,
            'project_id' => $project->id,
            'type' => 'inbound',
            'quantity' => 5,
            'total_cost' => 4000,
            'created_by' => $user->id,
        ]);
    }

    public function test_outbound_transaction_decreases_stock(): void
    {
        $user = $this->authorizedUser();
        $material = Material::create([
            'name' => '浪板',
            'unit' => '片',
            'current_stock' => 20,
        ]);

        $this
            ->actingAs($user)
            ->post(route('inventory-transactions.store'), [
                'material_id' => $material->id,
                'type' => 'outbound',
                'quantity' => 6,
                'unit' => '片',
                'unit_cost' => 450,
            ])
            ->assertRedirect();

        $this->assertSame('14.000', $material->refresh()->current_stock);
    }

    public function test_inventory_transaction_requires_material_type_quantity_and_unit(): void
    {
        $user = $this->authorizedUser();

        $this
            ->actingAs($user)
            ->from(route('inventory-transactions.create'))
            ->post(route('inventory-transactions.store'), [
                'type' => '',
                'quantity' => '',
                'unit' => '',
            ])
            ->assertRedirect(route('inventory-transactions.create'))
            ->assertSessionHasErrors(['material_id', 'type', 'quantity', 'unit']);
    }

    public function test_authenticated_user_can_update_transaction_and_recalculate_stock(): void
    {
        $user = $this->authorizedUser();
        $material = Material::create([
            'name' => '方管',
            'unit' => '支',
            'current_stock' => 15,
        ]);
        $transaction = InventoryTransaction::create([
            'material_id' => $material->id,
            'type' => 'inbound',
            'quantity' => 5,
            'unit' => '支',
            'unit_cost' => 1000,
            'total_cost' => 5000,
            'occurred_at' => now(),
        ]);
        $material->increment('current_stock', 5);

        $this
            ->actingAs($user)
            ->patch(route('inventory-transactions.update', $transaction), [
                'material_id' => $material->id,
                'type' => 'outbound',
                'quantity' => 3,
                'unit' => '支',
                'unit_cost' => 1000,
            ])
            ->assertRedirect(route('inventory-transactions.show', $transaction));

        $this->assertSame('12.000', $material->refresh()->current_stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'id' => $transaction->id,
            'type' => 'outbound',
            'quantity' => 3,
            'total_cost' => 3000,
        ]);
    }

    public function test_authenticated_user_can_delete_transaction_and_revert_stock(): void
    {
        $user = $this->authorizedUser();
        $material = Material::create([
            'name' => '角鐵',
            'unit' => '支',
            'current_stock' => 10,
        ]);
        $transaction = InventoryTransaction::create([
            'material_id' => $material->id,
            'type' => 'outbound',
            'quantity' => 4,
            'unit' => '支',
            'unit_cost' => 300,
            'total_cost' => 1200,
            'occurred_at' => now(),
        ]);
        $material->decrement('current_stock', 4);

        $this
            ->actingAs($user)
            ->delete(route('inventory-transactions.destroy', $transaction))
            ->assertRedirect(route('inventory-transactions.index'));

        $this->assertSame('10.000', $material->refresh()->current_stock);
        $this->assertDatabaseMissing('inventory_transactions', [
            'id' => $transaction->id,
        ]);
    }
}
