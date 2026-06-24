<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FinancialRecord;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use App\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectChangeOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_change_order_pages(): void
    {
        $this->get(route('project-change-orders.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_change_order_index(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();
        ProjectChangeOrder::create([
            'project_id' => $project->id,
            'title' => '追加排水槽',
            'amount' => 30000,
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('project-change-orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ProjectChangeOrders/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.title', '追加排水槽')
                ->where('orders.data.0.project.project_no', 'TPH-2026-0001')
                ->where('orders.data.0.project.customer.name', '追加單客戶')
                ->where('orders.data.0.financial_record', null)
                ->where('orders.data.0.can_submit_review', true)
                ->where('statuses.draft', '草稿')
            );

        $this->actingAs($user)
            ->get(route('project-change-orders.create', ['project_id' => $project->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ProjectChangeOrders/Create')
                ->where('order.project_id', (string) $project->id)
                ->where('order.status', 'draft')
                ->where('order.requires_formal_quotation', false)
                ->has('options.projects', 1)
                ->where('options.projects.0.customer.name', '追加單客戶')
            );
    }

    public function test_user_can_create_update_and_delete_change_order(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();

        $this->actingAs($user)->post(route('project-change-orders.store'), [
            'project_id' => $project->id,
            'title' => '追加側牆浪板',
            'description' => '客戶要求多做側牆封板',
            'amount' => 45000,
            'requested_date' => '2026-05-12',
            'status' => 'draft',
        ])->assertRedirect();

        $order = ProjectChangeOrder::where('title', '追加側牆浪板')->firstOrFail();
        $this->assertSame($user->id, $order->created_by);

        $this->actingAs($user)->patch(route('project-change-orders.update', $order), [
            'project_id' => $project->id,
            'title' => '追加側牆浪板與收邊',
            'description' => '客戶已確認追加範圍',
            'amount' => 52000,
            'requested_date' => '2026-05-12',
            'approved_date' => '2026-05-13',
            'status' => 'draft',
        ])->assertRedirect(route('project-change-orders.show', $order));

        $order->refresh();
        $this->assertSame('追加側牆浪板與收邊', $order->title);
        $this->assertSame('draft', $order->status);

        $this->actingAs($user)
            ->delete(route('project-change-orders.destroy', $order))
            ->assertRedirect(route('project-change-orders.index'));

        $this->assertDatabaseMissing('project_change_orders', ['id' => $order->id]);
    }

    public function test_confirmed_change_order_can_convert_to_financial_record(): void
    {
        $user = $this->authorizedUser(roleCode: 'accounting');
        $project = $this->project();
        $order = ProjectChangeOrder::create([
            'project_id' => $project->id,
            'title' => '追加雨遮',
            'description' => '追加入口雨遮',
            'amount' => 68000,
            'due_date' => '2026-05-30',
            'status' => 'customer_confirmed',
            'customer_note' => '客戶 LINE 確認',
        ]);

        $this->actingAs($user)
            ->post(route('project-change-orders.convert-financial-record', $order))
            ->assertRedirect();

        $order->refresh();
        $record = FinancialRecord::firstOrFail();

        $this->assertSame('converted', $order->status);
        $this->assertSame($record->id, $order->financial_record_id);
        $this->assertSame($order->id, $record->project_change_order_id);
        $this->assertSame('change_order', $record->type);
        $this->assertSame(68000, $record->amount);
        $this->assertSame('追加款 - 追加雨遮', $record->title);

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'convert',
            'event' => 'project_change_order.converted_to_financial_record',
            'subject_type' => ProjectChangeOrder::class,
            'subject_id' => $order->id,
            'module' => 'project_change_orders',
        ]);
    }

    public function test_pending_change_order_cannot_convert_to_financial_record(): void
    {
        $user = $this->authorizedUser(roleCode: 'accounting');
        $order = ProjectChangeOrder::create([
            'project_id' => $this->project()->id,
            'title' => '尚未確認追加',
            'amount' => 20000,
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->post(route('project-change-orders.convert-financial-record', $order))
            ->assertStatus(422);

        $this->assertDatabaseCount('financial_records', 0);
    }

    public function test_converted_change_order_cannot_convert_again_or_be_edited(): void
    {
        $user = $this->authorizedUser(roleCode: 'admin');
        $project = $this->project();
        $record = FinancialRecord::create([
            'project_id' => $project->id,
            'type' => 'change_order',
            'title' => '追加款',
            'amount' => 10000,
            'status' => 'pending',
        ]);
        $order = ProjectChangeOrder::create([
            'project_id' => $project->id,
            'financial_record_id' => $record->id,
            'title' => '已轉追加',
            'amount' => 10000,
            'status' => 'converted',
        ]);

        $this->actingAs($user)
            ->post(route('project-change-orders.convert-financial-record', $order))
            ->assertStatus(422);

        $this->actingAs($user)
            ->get(route('project-change-orders.edit', $order))
            ->assertStatus(422);
    }

    public function test_user_without_convert_capability_cannot_convert_change_order(): void
    {
        $user = $this->authorizedUser(roleCode: 'office');
        $order = ProjectChangeOrder::create([
            'project_id' => $this->project()->id,
            'title' => '追加採光板',
            'amount' => 30000,
            'status' => 'customer_confirmed',
        ]);

        $this->actingAs($user)
            ->post(route('project-change-orders.convert-financial-record', $order))
            ->assertForbidden();
    }

    public function test_change_order_workflow_submit_approve_and_confirm_customer(): void
    {
        $user = $this->authorizedUser();
        $order = ProjectChangeOrder::create([
            'project_id' => $this->project()->id,
            'title' => '追加防水收邊',
            'amount' => 18000,
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->post(route('project-change-orders.submit-review', $order))
            ->assertRedirect(route('project-change-orders.show', $order));
        $this->assertSame('pending_approval', $order->refresh()->status);

        $this->actingAs($user)
            ->post(route('project-change-orders.approve', $order))
            ->assertRedirect(route('project-change-orders.show', $order));
        $order->refresh();
        $this->assertSame('approved', $order->status);
        $this->assertSame($user->id, $order->approved_by);
        $this->assertNotNull($order->approved_at);

        $this->actingAs($user)
            ->post(route('project-change-orders.confirm-customer', $order))
            ->assertRedirect(route('project-change-orders.show', $order));
        $this->assertSame('customer_confirmed', $order->refresh()->status);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'project_change_order.submitted_for_review',
            'subject_id' => $order->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'project_change_order.approved',
            'subject_id' => $order->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'project_change_order.customer_confirmed',
            'subject_id' => $order->id,
        ]);
    }

    public function test_formal_change_order_creates_draft_quotation_and_requires_approved_quotation_before_confirming(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();
        $order = ProjectChangeOrder::create([
            'project_id' => $project->id,
            'title' => '追加 H 鋼補強',
            'description' => '需正式追加報價',
            'amount' => 120000,
            'status' => 'approved',
            'requires_formal_quotation' => true,
        ]);

        $this->actingAs($user)
            ->post(route('project-change-orders.confirm-customer', $order))
            ->assertStatus(422);

        $this->actingAs($user)
            ->post(route('project-change-orders.create-quotation', $order))
            ->assertRedirect();

        $order->refresh();
        $quotation = Quotation::firstOrFail();
        $this->assertSame($quotation->id, $order->quotation_id);
        $this->assertSame('draft', $quotation->status);
        $this->assertSame(120000, $quotation->total);
        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $quotation->id,
            'name' => '追加 H 鋼補強',
            'unit_price' => 120000,
        ]);

        $this->actingAs($user)
            ->post(route('project-change-orders.confirm-customer', $order))
            ->assertStatus(422);

        $quotation->update(['status' => 'approved']);

        $this->actingAs($user)
            ->post(route('project-change-orders.confirm-customer', $order))
            ->assertRedirect(route('project-change-orders.show', $order));
        $this->assertSame('customer_confirmed', $order->refresh()->status);
    }

    private function project(): Project
    {
        $customer = Customer::create(['name' => '追加單客戶']);

        return Project::create([
            'project_no' => 'TPH-2026-0001',
            'customer_id' => $customer->id,
            'name' => '追加單測試案件',
            'status' => 'in_progress',
        ]);
    }
}
