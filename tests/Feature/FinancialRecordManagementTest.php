<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FinancialRecord;
use App\Models\Project;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialRecordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_financial_record_pages(): void
    {
        $this->get(route('financial-records.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_financial_record_index(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();
        FinancialRecord::create([
            'project_id' => $project->id,
            'type' => 'deposit',
            'title' => '訂金',
            'amount' => 100000,
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->get(route('financial-records.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FinancialRecords/Index')
                ->has('records.data', 1)
            );
    }

    public function test_user_can_create_pending_record_and_overdue_is_marked(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();

        $this->actingAs($user)->post(route('financial-records.store'), [
            'project_id' => $project->id,
            'type' => 'deposit',
            'title' => '訂金',
            'amount' => 120000,
            'due_date' => now()->subDay()->toDateString(),
            'status' => 'pending',
        ])->assertRedirect();

        $this->assertDatabaseHas('financial_records', [
            'project_id' => $project->id,
            'title' => '訂金',
            'amount' => 120000,
            'status' => 'overdue',
        ]);
    }

    public function test_paid_record_gets_paid_date_when_blank(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();

        $this->actingAs($user)->post(route('financial-records.store'), [
            'project_id' => $project->id,
            'type' => 'final',
            'title' => '尾款',
            'amount' => 80000,
            'status' => 'paid',
        ])->assertRedirect();

        $record = FinancialRecord::where('title', '尾款')->firstOrFail();

        $this->assertSame(now()->toDateString(), $record->paid_date->toDateString());
        $this->assertSame('paid', $record->status);
    }

    public function test_financial_record_requires_project_title_amount_and_status(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->from(route('financial-records.create'))
            ->post(route('financial-records.store'), [
                'project_id' => '',
                'title' => '',
                'amount' => '',
                'type' => '',
                'status' => '',
            ])
            ->assertRedirect(route('financial-records.create'))
            ->assertSessionHasErrors(['project_id', 'type', 'title', 'amount', 'status']);
    }

    public function test_user_can_update_and_delete_financial_record(): void
    {
        $user = $this->authorizedUser();
        $project = $this->project();
        $record = FinancialRecord::create([
            'project_id' => $project->id,
            'type' => 'progress',
            'title' => '期中款',
            'amount' => 100000,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->patch(route('financial-records.update', $record), [
            'project_id' => $project->id,
            'type' => 'change_order',
            'title' => '追加款',
            'amount' => 150000,
            'status' => 'paid',
        ])->assertRedirect(route('financial-records.show', $record));

        $record->refresh();
        $this->assertSame('追加款', $record->title);
        $this->assertSame('paid', $record->status);

        $this->actingAs($user)
            ->delete(route('financial-records.destroy', $record))
            ->assertRedirect(route('financial-records.index'));

        $this->assertDatabaseMissing('financial_records', ['id' => $record->id]);
    }

    public function test_invoice_pdf_view_contains_project_records_payment_and_terms(): void
    {
        $project = $this->project();
        $record = FinancialRecord::create([
            'project_id' => $project->id,
            'type' => 'deposit',
            'title' => '訂金',
            'amount' => 120000,
            'due_date' => '2026-05-20',
            'status' => 'pending',
            'note' => '請於施工前付款',
        ]);

        $html = view('pdf.invoice', [
            'project' => $project->load('customer'),
            'records' => collect([$record]),
            'types' => ['deposit' => '訂金'],
            'statuses' => ['pending' => '待收款'],
            'settings' => [
                'company' => [
                    'name' => '鼎盛鐵皮工程',
                    'phone' => '02-1234-5678',
                    'address' => '台北市信義區',
                    'tax_id' => '12345678',
                ],
                'payment' => [
                    'bank_name' => '第一銀行',
                    'bank_code' => '007',
                    'account_number' => '1234567890',
                    'account_name' => '鼎盛鐵皮工程有限公司',
                ],
                'invoice' => [
                    'default_terms' => '請於到期日前完成匯款。',
                ],
            ],
            'total' => 120000,
            'issuedAt' => now(),
        ])->render();

        $this->assertStringContainsString('請款單', $html);
        $this->assertStringContainsString('鼎盛鐵皮工程', $html);
        $this->assertStringContainsString('收款客戶', $html);
        $this->assertStringContainsString('TPH-2026-0001', $html);
        $this->assertStringContainsString('訂金', $html);
        $this->assertStringContainsString('NT$ 120,000', $html);
        $this->assertStringContainsString('第一銀行', $html);
        $this->assertStringContainsString('1234567890', $html);
        $this->assertStringContainsString('請於到期日前完成匯款。', $html);
    }

    public function test_user_can_preview_combined_invoice_pdf_for_pending_and_overdue_records(): void
    {
        $user = $this->authorizedUser(roleCode: 'accounting');
        $project = $this->project();
        $deposit = FinancialRecord::create([
            'project_id' => $project->id,
            'type' => 'deposit',
            'title' => '訂金',
            'amount' => 120000,
            'status' => 'pending',
        ]);
        $progress = FinancialRecord::create([
            'project_id' => $project->id,
            'type' => 'progress',
            'title' => '期中款',
            'amount' => 80000,
            'status' => 'overdue',
        ]);
        $this->seedPaymentSettings();

        $this
            ->actingAs($user)
            ->get($this->invoiceUrl($project, [$deposit->id, $progress->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="TPH-2026-0001-invoice.pdf"');

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'export_pdf',
            'event' => 'financial_record.invoice_pdf_exported',
            'subject_type' => Project::class,
            'subject_id' => $project->id,
            'module' => 'financial_records',
        ]);
    }

    public function test_paid_or_cancelled_records_cannot_be_included_in_invoice_pdf(): void
    {
        $user = $this->authorizedUser(roleCode: 'accounting');
        $project = $this->project();
        $paid = FinancialRecord::create([
            'project_id' => $project->id,
            'type' => 'deposit',
            'title' => '已收訂金',
            'amount' => 120000,
            'status' => 'paid',
        ]);

        $this
            ->actingAs($user)
            ->get($this->invoiceUrl($project, [$paid->id]))
            ->assertStatus(422);
    }

    public function test_records_from_other_projects_cannot_be_included_in_invoice_pdf(): void
    {
        $user = $this->authorizedUser(roleCode: 'accounting');
        $project = $this->project();
        $other = Project::create([
            'project_no' => 'TPH-2026-0002',
            'customer_id' => $project->customer_id,
            'name' => '其他案件',
            'status' => 'billing',
        ]);
        $record = FinancialRecord::create([
            'project_id' => $other->id,
            'type' => 'deposit',
            'title' => '其他案件訂金',
            'amount' => 120000,
            'status' => 'pending',
        ]);

        $this
            ->actingAs($user)
            ->get($this->invoiceUrl($project, [$record->id]))
            ->assertStatus(422);
    }

    public function test_invoice_pdf_requires_selected_records(): void
    {
        $user = $this->authorizedUser(roleCode: 'accounting');
        $project = $this->project();

        $this
            ->actingAs($user)
            ->from(route('projects.show', $project))
            ->get(route('projects.invoice-pdf', $project))
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('financial_record_ids');
    }

    public function test_user_without_export_pdf_capability_cannot_preview_invoice_pdf(): void
    {
        $user = $this->authorizedUser(roleCode: 'sales');
        $project = $this->project();
        $record = FinancialRecord::create([
            'project_id' => $project->id,
            'type' => 'deposit',
            'title' => '訂金',
            'amount' => 120000,
            'status' => 'pending',
        ]);

        $this
            ->actingAs($user)
            ->get($this->invoiceUrl($project, [$record->id]))
            ->assertForbidden();
    }

    private function project(): Project
    {
        $customer = Customer::create(['name' => '收款客戶']);

        return Project::create([
            'project_no' => 'TPH-2026-0001',
            'customer_id' => $customer->id,
            'name' => '收款測試案件',
            'status' => 'billing',
            'contract_amount' => 500000,
        ]);
    }

    private function invoiceUrl(Project $project, array $recordIds): string
    {
        return route('projects.invoice-pdf', $project).'?'.http_build_query([
            'financial_record_ids' => $recordIds,
        ]);
    }

    private function seedPaymentSettings(): void
    {
        foreach ([
            'payment.bank_name' => '第一銀行',
            'payment.bank_code' => '007',
            'payment.account_number' => '1234567890',
            'payment.account_name' => '鼎盛鐵皮工程有限公司',
            'invoice.default_terms' => '請於到期日前完成匯款。',
        ] as $key => $value) {
            SystemSetting::create([
                'key' => $key,
                'type' => 'string',
                'value' => ['value' => $value],
            ]);
        }
    }
}
