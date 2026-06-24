<?php

namespace Tests\Feature;

use App\Models\Capability;
use App\Models\Customer;
use App\Models\FinancialRecord;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_workflow_api_transitions_share_actions_and_presenter_shape(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['write:quotations']);

        $quotation = $this->quotation('Q-API-WRITE-0001', 'draft');

        $this
            ->postJson(route('api.quotations.submit-review', $quotation))
            ->assertOk()
            ->assertJsonPath('message', '報價單已送審。')
            ->assertJsonPath('quotation.quotation_no', 'Q-API-WRITE-0001')
            ->assertJsonPath('quotation.status', 'reviewing')
            ->assertJsonPath('statuses.reviewing', '審核中');

        $this
            ->postJson(route('api.quotations.approve', $quotation))
            ->assertOk()
            ->assertJsonPath('quotation.status', 'approved')
            ->assertJsonPath('quotation.approver.id', $user->id);

        $this
            ->postJson(route('api.quotations.send-customer', $quotation))
            ->assertOk()
            ->assertJsonPath('quotation.status', 'sent')
            ->assertJsonPath('quotation.customer_confirmation_status', 'pending');

        $this
            ->postJson(route('api.quotations.accept-customer', $quotation), [
                'customer_confirmed_by_name' => 'API 客戶代表',
            ])
            ->assertOk()
            ->assertJsonPath('quotation.status', 'accepted')
            ->assertJsonPath('quotation.customer_confirmation_status', 'accepted')
            ->assertJsonPath('quotation.customer_confirmed_by_name', 'API 客戶代表');

        $quotation->refresh();

        $this->assertSame('accepted', $quotation->status);
        $this->assertSame($user->id, $quotation->approved_by);
        $this->assertNotNull($quotation->locked_at);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'accept_customer',
            'event' => 'quotation.customer_accepted',
            'subject_type' => Quotation::class,
            'subject_id' => $quotation->id,
            'module' => 'quotations',
        ]);
    }

    public function test_quotation_api_can_reject_reviewing_quotation(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['write:quotations']);

        $quotation = $this->quotation('Q-API-REJECT-0001', 'reviewing', [
            'approved_by' => $user->id,
        ]);

        $this
            ->postJson(route('api.quotations.reject', $quotation))
            ->assertOk()
            ->assertJsonPath('quotation.status', 'draft')
            ->assertJsonPath('quotation.approver', null);

        $quotation->refresh();

        $this->assertSame('draft', $quotation->status);
        $this->assertNull($quotation->approved_by);
    }

    public function test_quotation_api_can_void_and_reopen_quotation(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['write:quotations']);

        $quotation = $this->quotation('Q-API-REOPEN-0001', 'sent');
        $quotation->items()->create([
            'name' => 'API 原報價項目',
            'unit' => '式',
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
        ]);

        $this
            ->postJson(route('api.quotations.void', $quotation), [
                'void_reason' => 'API 改版',
            ])
            ->assertOk()
            ->assertJsonPath('quotation.status', 'voided')
            ->assertJsonPath('quotation.void_reason', 'API 改版');

        $this
            ->postJson(route('api.quotations.reopen', $quotation))
            ->assertOk()
            ->assertJsonPath('quotation.status', 'draft')
            ->assertJsonPath('quotation.reopened_from.id', $quotation->id)
            ->assertJsonPath('quotation.items.0.name', 'API 原報價項目');

        $newQuotation = Quotation::where('reopened_from_id', $quotation->id)->firstOrFail();

        $this->assertSame('draft', $newQuotation->status);
        $this->assertSame($user->id, $newQuotation->created_by);
        $this->assertSame(1, $newQuotation->items()->count());
    }

    public function test_quotation_api_write_requires_write_token_ability(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['read:quotations']);

        $quotation = $this->quotation('Q-API-NO-WRITE', 'draft');

        $this
            ->postJson(route('api.quotations.submit-review', $quotation))
            ->assertForbidden();

        $this->assertSame('draft', $quotation->refresh()->status);
    }

    public function test_quotation_api_write_requires_matching_rbac_capability(): void
    {
        $user = $this->authorizedUser(roleCode: 'sales');
        Sanctum::actingAs($user, ['write:quotations']);

        $quotation = $this->quotation('Q-API-NO-RBAC', 'reviewing');

        $this
            ->postJson(route('api.quotations.approve', $quotation))
            ->assertForbidden();

        $this->assertSame('reviewing', $quotation->refresh()->status);
    }

    public function test_quotation_api_write_keeps_project_linked_visibility(): void
    {
        $user = $this->userWithCapabilities([
            'sales.quotations.submit_review.tenant',
        ]);
        Sanctum::actingAs($user, ['write:quotations']);

        $customer = Customer::create(['name' => 'API IDOR 客戶']);
        $project = Project::create([
            'project_no' => 'TPH-API-WRITE-IDOR',
            'customer_id' => $customer->id,
            'manager_id' => User::factory()->create()->id,
            'name' => 'API 不可見工程',
            'status' => 'quoted',
        ]);
        $quotation = $this->quotation('Q-API-WRITE-IDOR', 'draft', [
            'customer_id' => $customer->id,
            'project_id' => $project->id,
        ]);

        $this
            ->postJson(route('api.quotations.submit-review', $quotation))
            ->assertForbidden();

        $this->assertSame('draft', $quotation->refresh()->status);
    }

    public function test_project_change_order_workflow_api_transitions_share_actions_and_presenter_shape(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['write:project-change-orders']);

        $order = $this->projectChangeOrder('API 追加防水', 'draft');

        $this
            ->postJson(route('api.project-change-orders.submit-review', $order))
            ->assertOk()
            ->assertJsonPath('message', '追加單已送審。')
            ->assertJsonPath('order.title', 'API 追加防水')
            ->assertJsonPath('order.status', 'pending_approval')
            ->assertJsonPath('statuses.pending_approval', '待主管核准');

        $this
            ->postJson(route('api.project-change-orders.approve', $order))
            ->assertOk()
            ->assertJsonPath('order.status', 'approved')
            ->assertJsonPath('order.approver.id', $user->id);

        $this
            ->postJson(route('api.project-change-orders.confirm-customer', $order))
            ->assertOk()
            ->assertJsonPath('order.status', 'customer_confirmed');

        $this
            ->postJson(route('api.project-change-orders.convert-financial-record', $order))
            ->assertOk()
            ->assertJsonPath('order.status', 'converted')
            ->assertJsonPath('order.financial_record.title', '追加款 - API 追加防水')
            ->assertJsonPath('order.financial_record.amount', 32000);

        $order->refresh();
        $record = FinancialRecord::firstOrFail();

        $this->assertSame('converted', $order->status);
        $this->assertSame($record->id, $order->financial_record_id);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'convert',
            'event' => 'project_change_order.converted_to_financial_record',
            'subject_type' => ProjectChangeOrder::class,
            'subject_id' => $order->id,
            'module' => 'project_change_orders',
        ]);
    }

    public function test_project_change_order_api_can_cancel_order(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['write:project-change-orders']);

        $order = $this->projectChangeOrder('API 取消追加', 'approved');

        $this
            ->postJson(route('api.project-change-orders.cancel', $order))
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath('order.can_cancel', true);

        $this->assertSame('cancelled', $order->refresh()->status);
    }

    public function test_project_change_order_api_creates_formal_quotation_and_requires_approval_before_confirming(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['write:project-change-orders']);

        $order = $this->projectChangeOrder('API 正式追加報價', 'approved', [
            'requires_formal_quotation' => true,
            'amount' => 120000,
        ]);

        $this
            ->postJson(route('api.project-change-orders.confirm-customer', $order))
            ->assertStatus(422);

        $this
            ->postJson(route('api.project-change-orders.create-quotation', $order))
            ->assertOk()
            ->assertJsonPath('order.quotation.status', 'draft')
            ->assertJsonPath('order.quotation.total', 120000);

        $order->refresh();
        $quotation = Quotation::firstOrFail();

        $this->assertSame($quotation->id, $order->quotation_id);
        $this->assertSame($user->id, $quotation->created_by);

        $this
            ->postJson(route('api.project-change-orders.confirm-customer', $order))
            ->assertStatus(422);

        $quotation->update(['status' => 'approved']);

        $this
            ->postJson(route('api.project-change-orders.confirm-customer', $order))
            ->assertOk()
            ->assertJsonPath('order.status', 'customer_confirmed')
            ->assertJsonPath('order.quotation.status', 'approved');
    }

    public function test_project_change_order_api_write_requires_write_token_ability(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['read:project-change-orders']);

        $order = $this->projectChangeOrder('API 無寫入 scope', 'draft');

        $this
            ->postJson(route('api.project-change-orders.submit-review', $order))
            ->assertForbidden();

        $this->assertSame('draft', $order->refresh()->status);
    }

    public function test_project_change_order_api_write_requires_matching_rbac_capability(): void
    {
        $user = $this->authorizedUser(roleCode: 'sales');
        Sanctum::actingAs($user, ['write:project-change-orders']);

        $order = $this->projectChangeOrder('API 無核准 RBAC', 'pending_approval');

        $this
            ->postJson(route('api.project-change-orders.approve', $order))
            ->assertForbidden();

        $this->assertSame('pending_approval', $order->refresh()->status);
    }

    public function test_project_change_order_api_write_keeps_project_visibility(): void
    {
        $user = $this->userWithCapabilities([
            'projects.change_orders.submit_review.tenant',
        ]);
        Sanctum::actingAs($user, ['write:project-change-orders']);

        $project = $this->projectForChangeOrder([
            'project_no' => 'TPH-API-PCO-IDOR',
            'manager_id' => User::factory()->create()->id,
        ]);
        $order = $this->projectChangeOrder('API 不可見追加', 'draft', [
            'project_id' => $project->id,
        ]);

        $this
            ->postJson(route('api.project-change-orders.submit-review', $order))
            ->assertForbidden();

        $this->assertSame('draft', $order->refresh()->status);
    }

    public function test_purchase_order_receive_api_updates_stock_and_returns_order_payload(): void
    {
        $user = $this->authorizedUser(roleCode: 'purchasing');
        Sanctum::actingAs($user, ['write:purchase-orders']);

        [$order, $item, $material] = $this->purchaseOrder();

        $this
            ->postJson(route('api.purchase-orders.receive', $order), [
                'received_at' => '2026-05-12 10:00:00',
                'items' => [
                    [
                        'id' => $item->id,
                        'received_quantity' => 4,
                        'note' => 'API 第一批到貨',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('message', '到貨驗收已完成，庫存已入庫。')
            ->assertJsonPath('order.status', 'partially_received')
            ->assertJsonPath('order.items.0.received_quantity', '4.000')
            ->assertJsonPath('order.items.0.material.current_stock', '9.000');

        $transaction = InventoryTransaction::firstOrFail();

        $this->assertSame('9.000', $material->refresh()->current_stock);
        $this->assertSame('4.000', $item->refresh()->received_quantity);
        $this->assertSame('purchase_in', $transaction->type);
        $this->assertSame($item->id, $transaction->purchase_order_item_id);
        $this->assertSame(3200, $transaction->total_cost);
    }

    public function test_purchase_order_receive_api_requires_write_token_ability(): void
    {
        $user = $this->authorizedUser(roleCode: 'purchasing');
        Sanctum::actingAs($user, ['write:inventory-transactions']);

        [$order, $item, $material] = $this->purchaseOrder();

        $this
            ->postJson(route('api.purchase-orders.receive', $order), [
                'items' => [
                    ['id' => $item->id, 'received_quantity' => 4],
                ],
            ])
            ->assertForbidden();

        $this->assertSame('5.000', $material->refresh()->current_stock);
        $this->assertSame('0.000', $item->refresh()->received_quantity);
    }

    public function test_purchase_order_api_can_create_update_and_delete_with_items(): void
    {
        $user = $this->authorizedUser(roleCode: 'purchasing');
        Sanctum::actingAs($user, ['write:purchase-orders']);
        $supplier = Supplier::create(['name' => 'API 建立供應商']);
        $material = Material::create([
            'name' => 'API 建立材料',
            'unit' => '支',
            'cost_price' => 700,
        ]);

        $createResponse = $this
            ->postJson(route('api.purchase-orders.store'), [
                'purchase_order_no' => 'PO-API-CRUD-0001',
                'supplier_id' => $supplier->id,
                'status' => 'draft',
                'ordered_date' => '2026-06-01',
                'tax' => 100,
                'discount' => 50,
                'items' => [
                    [
                        'material_id' => $material->id,
                        'name' => 'API 建立材料',
                        'unit' => '支',
                        'quantity' => 5,
                        'unit_cost' => 700,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('message', '採購單已建立。')
            ->assertJsonPath('order.purchase_order_no', 'PO-API-CRUD-0001')
            ->assertJsonPath('order.creator.id', $user->id)
            ->assertJsonPath('order.subtotal', 3500)
            ->assertJsonPath('order.total', 3550)
            ->assertJsonPath('order.items.0.subtotal', 3500);

        $order = PurchaseOrder::findOrFail($createResponse->json('order.id'));

        $this
            ->patchJson(route('api.purchase-orders.update', $order), [
                'purchase_order_no' => $order->purchase_order_no,
                'supplier_id' => $supplier->id,
                'status' => 'sent',
                'expected_date' => '2026-06-10',
                'tax' => 0,
                'discount' => 100,
                'items' => [
                    [
                        'material_id' => $material->id,
                        'name' => 'API 更新材料',
                        'unit' => '支',
                        'quantity' => 3,
                        'unit_cost' => 800,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('message', '採購單已更新。')
            ->assertJsonPath('order.status', 'sent')
            ->assertJsonPath('order.total', 2300)
            ->assertJsonPath('order.items.0.name', 'API 更新材料')
            ->assertJsonPath('order.items.0.quantity', '3.000');

        $this->assertSame(1, $order->items()->count());

        $this
            ->deleteJson(route('api.purchase-orders.destroy', $order))
            ->assertOk()
            ->assertJsonPath('message', '採購單已刪除。')
            ->assertJsonPath('deleted_purchase_order_id', $order->id);

        $this->assertDatabaseMissing('purchase_orders', [
            'id' => $order->id,
        ]);
    }

    public function test_purchase_order_api_create_requires_matching_rbac_capability(): void
    {
        $user = $this->authorizedUser(roleCode: 'sales');
        Sanctum::actingAs($user, ['write:purchase-orders']);
        $supplier = Supplier::create(['name' => 'API 無採購 RBAC 供應商']);
        $material = Material::create(['name' => 'API 無採購 RBAC 材料', 'unit' => '支']);

        $this
            ->postJson(route('api.purchase-orders.store'), [
                'purchase_order_no' => 'PO-API-NO-RBAC',
                'supplier_id' => $supplier->id,
                'status' => 'draft',
                'items' => [
                    [
                        'material_id' => $material->id,
                        'name' => $material->name,
                        'unit' => '支',
                        'quantity' => 1,
                        'unit_cost' => 100,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('purchase_orders', [
            'purchase_order_no' => 'PO-API-NO-RBAC',
        ]);
    }

    public function test_purchase_order_api_rejects_update_and_delete_after_receiving(): void
    {
        $user = $this->authorizedUser(roleCode: 'purchasing');
        Sanctum::actingAs($user, ['write:purchase-orders']);
        [$order, $item, $material] = $this->purchaseOrder();

        $order->update(['status' => 'partially_received']);
        $item->update(['received_quantity' => 1]);

        $this
            ->patchJson(route('api.purchase-orders.update', $order), [
                'purchase_order_no' => $order->purchase_order_no,
                'supplier_id' => $order->supplier_id,
                'status' => 'sent',
                'items' => [
                    [
                        'material_id' => $material->id,
                        'name' => $material->name,
                        'unit' => '支',
                        'quantity' => 2,
                        'unit_cost' => 800,
                    ],
                ],
            ])
            ->assertStatus(422);

        $this
            ->deleteJson(route('api.purchase-orders.destroy', $order))
            ->assertStatus(422);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $order->id,
            'status' => 'partially_received',
        ]);
        $this->assertSame('1.000', $item->refresh()->received_quantity);
    }

    public function test_inventory_transaction_api_can_create_update_and_delete_with_stock_recalculation(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['write:inventory-transactions']);

        $material = Material::create([
            'name' => 'API 方管',
            'unit' => '支',
            'current_stock' => 10,
        ]);

        $createResponse = $this
            ->postJson(route('api.inventory-transactions.store'), [
                'material_id' => $material->id,
                'type' => 'inbound',
                'quantity' => 5,
                'unit' => '支',
                'unit_cost' => 800,
                'reference_no' => 'API-IN-001',
                'occurred_at' => '2026-06-01 09:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('message', '庫存異動已建立。')
            ->assertJsonPath('transaction.type', 'inbound')
            ->assertJsonPath('transaction.material.current_stock', '15.000')
            ->assertJsonPath('transaction.total_cost', 4000);

        $transaction = InventoryTransaction::findOrFail($createResponse->json('transaction.id'));
        $this->assertSame('15.000', $material->refresh()->current_stock);

        $this
            ->patchJson(route('api.inventory-transactions.update', $transaction), [
                'material_id' => $material->id,
                'type' => 'outbound',
                'quantity' => 3,
                'unit' => '支',
                'unit_cost' => 800,
                'reference_no' => 'API-OUT-001',
            ])
            ->assertOk()
            ->assertJsonPath('message', '庫存異動已更新。')
            ->assertJsonPath('transaction.type', 'outbound')
            ->assertJsonPath('transaction.material.current_stock', '7.000')
            ->assertJsonPath('transaction.total_cost', 2400);

        $this->assertSame('7.000', $material->refresh()->current_stock);

        $this
            ->deleteJson(route('api.inventory-transactions.destroy', $transaction))
            ->assertOk()
            ->assertJsonPath('message', '庫存異動已刪除。')
            ->assertJsonPath('deleted_transaction_id', $transaction->id);

        $this->assertSame('10.000', $material->refresh()->current_stock);
        $this->assertDatabaseMissing('inventory_transactions', [
            'id' => $transaction->id,
        ]);
    }

    public function test_inventory_transaction_api_requires_write_token_ability(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['write:purchase-orders']);
        $material = Material::create(['name' => 'API 無庫存 scope', 'unit' => '支']);

        $this
            ->postJson(route('api.inventory-transactions.store'), [
                'material_id' => $material->id,
                'type' => 'inbound',
                'quantity' => 5,
                'unit' => '支',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_inventory_transaction_api_keeps_project_visibility_on_create(): void
    {
        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'inventory.inventory_transactions.create.tenant',
        ]);
        Sanctum::actingAs($user, ['write:inventory-transactions']);
        $material = Material::create(['name' => 'API IDOR 材料', 'unit' => '支']);
        $project = $this->projectForChangeOrder([
            'project_no' => 'TPH-API-INV-IDOR',
            'manager_id' => User::factory()->create()->id,
        ]);

        $this
            ->postJson(route('api.inventory-transactions.store'), [
                'material_id' => $material->id,
                'project_id' => $project->id,
                'type' => 'inbound',
                'quantity' => 5,
                'unit' => '支',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->assertSame('0.000', $material->refresh()->current_stock);
    }

    public function test_material_api_can_create_update_and_delete_without_bypassing_stock_flow(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['write:materials']);

        $createResponse = $this
            ->postJson(route('api.materials.store'), [
                'category_name' => 'API 材料分類',
                'name' => 'API 新材料',
                'spec' => 'T=3mm',
                'unit' => '片',
                'cost_price' => 1200,
                'sale_price' => 1500,
                'safe_stock' => 5,
                'current_stock' => 7,
            ])
            ->assertCreated()
            ->assertJsonPath('message', '材料品項已建立。')
            ->assertJsonPath('material.name', 'API 新材料')
            ->assertJsonPath('material.category.name', 'API 材料分類')
            ->assertJsonPath('material.current_stock', '7.000');

        $material = Material::findOrFail($createResponse->json('material.id'));

        $this
            ->patchJson(route('api.materials.update', $material), [
                'name' => 'API 新材料改',
                'spec' => 'T=4mm',
                'unit' => '片',
                'cost_price' => 1300,
                'sale_price' => 1700,
                'safe_stock' => 4,
                'current_stock' => 999,
            ])
            ->assertOk()
            ->assertJsonPath('message', '材料品項已更新。')
            ->assertJsonPath('material.name', 'API 新材料改')
            ->assertJsonPath('material.current_stock', '7.000');

        $this->assertSame('7.000', $material->refresh()->current_stock);

        $this
            ->deleteJson(route('api.materials.destroy', $material))
            ->assertOk()
            ->assertJsonPath('message', '材料品項已刪除。')
            ->assertJsonPath('deleted_material_id', $material->id);

        $this->assertDatabaseMissing('materials', [
            'id' => $material->id,
        ]);
    }

    public function test_material_api_write_requires_write_token_ability(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['read:materials']);

        $this
            ->postJson(route('api.materials.store'), [
                'name' => 'API 無寫入材料',
                'unit' => '支',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('materials', [
            'name' => 'API 無寫入材料',
        ]);
    }

    public function test_material_api_write_requires_matching_rbac_capability(): void
    {
        $user = $this->authorizedUser(roleCode: 'sales');
        Sanctum::actingAs($user, ['write:materials']);

        $material = Material::create([
            'name' => 'API 無 RBAC 材料',
            'unit' => '支',
        ]);

        $this
            ->deleteJson(route('api.materials.destroy', $material))
            ->assertForbidden();

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function quotation(string $quotationNo, string $status, array $attributes = []): Quotation
    {
        $customerId = $attributes['customer_id'] ?? Customer::create(['name' => $quotationNo.' 客戶'])->id;

        return Quotation::create([
            'quotation_no' => $quotationNo,
            'customer_id' => $customerId,
            'status' => $status,
            'subtotal' => 1000,
            'total' => 1000,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function projectChangeOrder(string $title, string $status, array $attributes = []): ProjectChangeOrder
    {
        $projectId = $attributes['project_id'] ?? $this->projectForChangeOrder()->id;

        return ProjectChangeOrder::create([
            'project_id' => $projectId,
            'title' => $title,
            'description' => $title.' 說明',
            'amount' => 32000,
            'status' => $status,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function projectForChangeOrder(array $attributes = []): Project
    {
        $customer = Customer::create(['name' => ($attributes['project_no'] ?? 'TPH-API-PCO').' 客戶']);

        return Project::create([
            'project_no' => 'TPH-API-PCO-'.str()->random(8),
            'customer_id' => $customer->id,
            'name' => 'API 追加單工程',
            'status' => 'in_progress',
            ...$attributes,
        ]);
    }

    /**
     * @return array{0: PurchaseOrder, 1: PurchaseOrderItem, 2: Material}
     */
    private function purchaseOrder(): array
    {
        $supplier = Supplier::create(['name' => 'API 鋼鐵供應商']);
        $material = Material::create([
            'name' => 'API C 型鋼',
            'unit' => '支',
            'cost_price' => 800,
            'current_stock' => 5,
        ]);
        $order = PurchaseOrder::create([
            'purchase_order_no' => 'PO-API-0001-'.str()->random(6),
            'supplier_id' => $supplier->id,
            'status' => 'sent',
            'total' => 8000,
        ]);
        $item = $order->items()->create([
            'material_id' => $material->id,
            'name' => 'API C 型鋼',
            'unit' => '支',
            'quantity' => 10,
            'unit_cost' => 800,
            'subtotal' => 8000,
        ]);

        return [$order, $item, $material];
    }

    /**
     * @param  array<int, string>  $capabilityCodes
     */
    private function userWithCapabilities(array $capabilityCodes): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'API Write Role '.str()->random(8),
            'code' => 'api_write_role_'.str()->random(8),
        ]);

        $role->capabilities()->sync(
            Capability::query()
                ->whereIn('code', $capabilityCodes)
                ->pluck('id'),
        );
        $user->roles()->attach($role);

        return $user;
    }
}
