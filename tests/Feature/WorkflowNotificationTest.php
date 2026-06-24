<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkflowNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_submit_notifies_approvers_without_notifying_actor(): void
    {
        Notification::fake();

        $actor = $this->authorizedUser(roleCode: 'sales');
        $approver = $this->userWithRole('admin');
        $quotation = $this->quotation('Q-NOTIFY-0001', 'draft');

        $this
            ->actingAs($actor)
            ->post(route('quotations.submit-review', $quotation))
            ->assertRedirect(route('quotations.show', $quotation));

        Notification::assertSentTo(
            $approver,
            WorkflowNotification::class,
            fn (WorkflowNotification $notification) => $notification->title === '報價單 Q-NOTIFY-0001 已送審'
                && $notification->module === 'quotations'
                && $notification->payload['quotation_id'] === $quotation->id,
        );
        Notification::assertNotSentTo($actor, WorkflowNotification::class);
    }

    public function test_project_change_order_customer_confirmation_notifies_financial_record_converters(): void
    {
        Notification::fake();

        $actor = $this->authorizedUser(roleCode: 'site_manager');
        $accounting = $this->userWithRole('accounting');
        $order = ProjectChangeOrder::create([
            'project_id' => $this->project()->id,
            'title' => '通知追加單',
            'amount' => 36000,
            'status' => 'approved',
        ]);

        $this
            ->actingAs($actor)
            ->post(route('project-change-orders.confirm-customer', $order))
            ->assertRedirect(route('project-change-orders.show', $order));

        Notification::assertSentTo(
            $accounting,
            WorkflowNotification::class,
            fn (WorkflowNotification $notification) => $notification->title === '追加單 通知追加單 已取得客戶確認'
                && $notification->module === 'project_change_orders'
                && $notification->payload['project_change_order_id'] === $order->id,
        );
        Notification::assertNotSentTo($actor, WorkflowNotification::class);
    }

    public function test_purchase_order_receive_notifies_purchase_viewers_without_notifying_actor(): void
    {
        Notification::fake();

        $actor = $this->authorizedUser(roleCode: 'purchasing');
        $recipient = $this->userWithRole('purchasing');
        [$order, $item] = $this->purchaseOrder();

        $this
            ->actingAs($actor)
            ->post(route('purchase-orders.receive', $order), [
                'items' => [
                    ['id' => $item->id, 'received_quantity' => 2],
                ],
            ])
            ->assertRedirect(route('purchase-orders.show', $order));

        Notification::assertSentTo(
            $recipient,
            WorkflowNotification::class,
            fn (WorkflowNotification $notification) => $notification->title === "採購單 {$order->purchase_order_no} 已到貨驗收"
                && $notification->module === 'purchase_orders'
                && $notification->payload['purchase_order_id'] === $order->id,
        );
        Notification::assertNotSentTo($actor, WorkflowNotification::class);
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role);

        return $user;
    }

    private function quotation(string $quotationNo, string $status): Quotation
    {
        return Quotation::create([
            'quotation_no' => $quotationNo,
            'customer_id' => Customer::create(['name' => $quotationNo.' 客戶'])->id,
            'status' => $status,
            'subtotal' => 1000,
            'total' => 1000,
        ]);
    }

    private function project(): Project
    {
        return Project::create([
            'project_no' => 'TPH-NOTIFY-'.str()->random(6),
            'customer_id' => Customer::create(['name' => '通知客戶'])->id,
            'name' => '通知工程',
            'status' => 'in_progress',
        ]);
    }

    /**
     * @return array{0: PurchaseOrder, 1: PurchaseOrderItem}
     */
    private function purchaseOrder(): array
    {
        $supplier = Supplier::create(['name' => '通知供應商']);
        $material = Material::create([
            'name' => '通知材料',
            'unit' => '支',
            'current_stock' => 5,
        ]);
        $order = PurchaseOrder::create([
            'purchase_order_no' => 'PO-NOTIFY-'.str()->random(6),
            'supplier_id' => $supplier->id,
            'status' => 'sent',
        ]);
        $item = $order->items()->create([
            'material_id' => $material->id,
            'name' => '通知材料',
            'unit' => '支',
            'quantity' => 5,
            'unit_cost' => 300,
            'subtotal' => 1500,
        ]);

        return [$order, $item];
    }
}
