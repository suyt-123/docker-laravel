<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_supplier_and_purchase_pages(): void
    {
        $this->get(route('suppliers.index'))->assertRedirect(route('login'));
        $this->get(route('purchase-orders.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_manage_suppliers(): void
    {
        $user = $this->authorizedUser(roleCode: 'purchasing');

        $this->actingAs($user)->post(route('suppliers.store'), [
            'name' => '鋼鐵供應商',
            'contact_name' => '王先生',
            'phone' => '02-1234-5678',
            'email' => 'sales@example.com',
            'tax_id' => '12345678',
            'payment_terms' => '月結 30 天',
            'is_active' => true,
        ])->assertRedirect();

        $supplier = Supplier::where('name', '鋼鐵供應商')->firstOrFail();

        $this->actingAs($user)->patch(route('suppliers.update', $supplier), [
            'name' => '鋼鐵供應商 A',
            'contact_name' => '王先生',
            'phone' => '02-1234-5678',
            'email' => 'sales@example.com',
            'tax_id' => '12345678',
            'payment_terms' => '月結 45 天',
            'is_active' => false,
        ])->assertRedirect(route('suppliers.show', $supplier));

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => '鋼鐵供應商 A',
            'is_active' => false,
        ]);
    }

    public function test_user_can_create_purchase_order_with_items(): void
    {
        $user = $this->authorizedUser(roleCode: 'purchasing');
        $supplier = Supplier::create(['name' => '鋼鐵供應商']);
        $material = Material::create([
            'name' => 'C 型鋼',
            'unit' => '支',
            'cost_price' => 800,
            'current_stock' => 5,
        ]);

        $this->actingAs($user)->post(route('purchase-orders.store'), [
            'purchase_order_no' => 'PO-2026-0101',
            'supplier_id' => $supplier->id,
            'status' => 'sent',
            'ordered_date' => '2026-05-12',
            'expected_date' => '2026-05-18',
            'tax' => 100,
            'discount' => 50,
            'items' => [
                [
                    'material_id' => $material->id,
                    'name' => 'C 型鋼',
                    'unit' => '支',
                    'quantity' => 10,
                    'unit_cost' => 800,
                ],
            ],
        ])->assertRedirect();

        $order = PurchaseOrder::where('purchase_order_no', 'PO-2026-0101')->firstOrFail();

        $this->assertSame($user->id, $order->created_by);
        $this->assertSame(8000, $order->subtotal);
        $this->assertSame(8050, $order->total);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $order->id,
            'material_id' => $material->id,
            'quantity' => '10.000',
            'received_quantity' => '0.000',
        ]);
    }

    public function test_purchase_order_number_is_generated_when_blank(): void
    {
        $user = $this->authorizedUser(roleCode: 'purchasing');
        $supplier = Supplier::create(['name' => '鋼鐵供應商']);
        $material = Material::create(['name' => '方管', 'unit' => '支']);

        $this->actingAs($user)->post(route('purchase-orders.store'), [
            'purchase_order_no' => '',
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'items' => [
                [
                    'material_id' => $material->id,
                    'name' => '方管',
                    'unit' => '支',
                    'quantity' => 2,
                    'unit_cost' => 500,
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', [
            'purchase_order_no' => 'PO-'.now()->format('Y').'-0001',
            'total' => 1000,
        ]);
    }

    public function test_purchase_order_receive_creates_inventory_transaction_and_updates_stock(): void
    {
        $user = $this->authorizedUser(roleCode: 'purchasing');
        $supplier = Supplier::create(['name' => '鋼鐵供應商']);
        $material = Material::create([
            'name' => 'C 型鋼',
            'unit' => '支',
            'cost_price' => 800,
            'current_stock' => 5,
        ]);
        $order = PurchaseOrder::create([
            'purchase_order_no' => 'PO-2026-0001',
            'supplier_id' => $supplier->id,
            'status' => 'sent',
            'total' => 8000,
        ]);
        $item = $order->items()->create([
            'material_id' => $material->id,
            'name' => 'C 型鋼',
            'unit' => '支',
            'quantity' => 10,
            'unit_cost' => 800,
            'subtotal' => 8000,
        ]);

        $this->actingAs($user)->post(route('purchase-orders.receive', $order), [
            'received_at' => '2026-05-12 10:00:00',
            'items' => [
                [
                    'id' => $item->id,
                    'received_quantity' => 4,
                    'note' => '第一批到貨',
                ],
            ],
        ])->assertRedirect(route('purchase-orders.show', $order));

        $order->refresh();
        $material->refresh();
        $item->refresh();
        $transaction = InventoryTransaction::firstOrFail();

        $this->assertSame('partially_received', $order->status);
        $this->assertSame('9.000', $material->current_stock);
        $this->assertSame('4.000', $item->received_quantity);
        $this->assertSame('purchase_in', $transaction->type);
        $this->assertSame($item->id, $transaction->purchase_order_item_id);
        $this->assertSame(3200, $transaction->total_cost);

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'receive',
            'event' => 'purchase_order.received',
            'subject_type' => PurchaseOrder::class,
            'subject_id' => $order->id,
            'module' => 'purchase_orders',
        ]);
    }

    public function test_purchase_order_completed_when_all_items_received(): void
    {
        $user = $this->authorizedUser(roleCode: 'purchasing');
        $supplier = Supplier::create(['name' => '鋼鐵供應商']);
        $material = Material::create(['name' => '方管', 'unit' => '支']);
        $order = PurchaseOrder::create([
            'purchase_order_no' => 'PO-2026-0002',
            'supplier_id' => $supplier->id,
            'status' => 'sent',
        ]);
        $item = $order->items()->create([
            'material_id' => $material->id,
            'name' => '方管',
            'unit' => '支',
            'quantity' => 3,
            'unit_cost' => 500,
            'subtotal' => 1500,
        ]);

        $this->actingAs($user)->post(route('purchase-orders.receive', $order), [
            'items' => [
                ['id' => $item->id, 'received_quantity' => 3],
            ],
        ])->assertRedirect(route('purchase-orders.show', $order));

        $this->assertSame('completed', $order->refresh()->status);
    }

    public function test_receive_rejects_over_receiving_and_draft_orders(): void
    {
        $user = $this->authorizedUser(roleCode: 'purchasing');
        $supplier = Supplier::create(['name' => '鋼鐵供應商']);
        $material = Material::create(['name' => '方管', 'unit' => '支']);
        $order = PurchaseOrder::create([
            'purchase_order_no' => 'PO-2026-0003',
            'supplier_id' => $supplier->id,
            'status' => 'draft',
        ]);
        $item = $order->items()->create([
            'material_id' => $material->id,
            'name' => '方管',
            'unit' => '支',
            'quantity' => 3,
            'unit_cost' => 500,
            'subtotal' => 1500,
        ]);

        $this->actingAs($user)->post(route('purchase-orders.receive', $order), [
            'items' => [
                ['id' => $item->id, 'received_quantity' => 1],
            ],
        ])->assertStatus(422);

        $order->update(['status' => 'sent']);

        $this->actingAs($user)->post(route('purchase-orders.receive', $order), [
            'items' => [
                ['id' => $item->id, 'received_quantity' => 4],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('inventory_transactions', 0);
    }
}
