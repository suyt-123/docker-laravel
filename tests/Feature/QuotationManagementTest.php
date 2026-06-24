<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DocumentAttachment;
use App\Models\Material;
use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuotationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_quotation_pages(): void
    {
        $this->get(route('quotations.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_quotation_index(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '報價客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-0001',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'subtotal' => 1000,
            'total' => 1000,
        ]);
        $quotation->items()->create([
            'name' => 'C 型鋼',
            'unit' => '支',
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Quotations/Index')
                ->has('quotations.data', 1)
                ->where('quotations.data.0.quotation_no', 'Q-2026-0001')
                ->where('quotations.data.0.customer.name', '報價客戶')
                ->where('quotations.data.0.items_count', 1)
                ->where('statuses.draft', '草稿')
            );
    }

    public function test_authenticated_user_can_create_quotation_with_items(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '鐵皮屋客戶']);
        $project = Project::create([
            'project_no' => 'TPH-2026-0001',
            'customer_id' => $customer->id,
            'name' => '五股廠房',
            'status' => 'quoted',
        ]);
        $material = Material::create([
            'name' => 'C 型鋼',
            'spec' => '100x50x20x2.3mm',
            'unit' => '支',
            'cost_price' => 850,
            'sale_price' => 1100,
        ]);

        $this
            ->actingAs($user)
            ->post(route('quotations.store'), [
                'quotation_no' => 'Q-2026-0101',
                'customer_id' => $customer->id,
                'project_id' => $project->id,
                'status' => 'draft',
                'profit_rate' => 25,
                'tax' => 100,
                'discount' => 50,
                'items' => [
                    [
                        'material_id' => $material->id,
                        'name' => 'C 型鋼',
                        'spec' => '100x50x20x2.3mm',
                        'unit' => '支',
                        'quantity' => 2,
                        'unit_price' => 1100,
                        'cost_price' => 850,
                        'waste_rate' => 5,
                    ],
                ],
            ])
            ->assertRedirect();

        $quotation = Quotation::where('quotation_no', 'Q-2026-0101')->firstOrFail();

        $this->assertSame(2200, $quotation->subtotal);
        $this->assertSame(2250, $quotation->total);
        $this->assertSame($user->id, $quotation->created_by);
        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $quotation->id,
            'material_id' => $material->id,
            'subtotal' => 2200,
        ]);
    }

    public function test_quotation_number_is_generated_when_blank(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '自動報價客戶']);

        $this
            ->actingAs($user)
            ->post(route('quotations.store'), [
                'quotation_no' => '',
                'customer_id' => $customer->id,
                'status' => 'draft',
                'items' => [
                    [
                        'name' => '自訂施工',
                        'unit' => '式',
                        'quantity' => 1,
                        'unit_price' => 5000,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quotations', [
            'quotation_no' => 'Q-'.now()->format('Y').'-0001',
            'total' => 5000,
        ]);
    }

    public function test_quotation_requires_customer_and_items(): void
    {
        $user = $this->authorizedUser();

        $this
            ->actingAs($user)
            ->from(route('quotations.create'))
            ->post(route('quotations.store'), [
                'status' => 'draft',
                'items' => [],
            ])
            ->assertRedirect(route('quotations.create'))
            ->assertSessionHasErrors(['customer_id', 'items']);
    }

    public function test_authenticated_user_can_update_quotation_and_replace_items(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '更新報價客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-0002',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'subtotal' => 1000,
            'total' => 1000,
        ]);
        $quotation->items()->create([
            'name' => '舊項目',
            'unit' => '式',
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->patch(route('quotations.update', $quotation), [
                'quotation_no' => 'Q-2026-0002',
                'customer_id' => $customer->id,
                'status' => 'sent',
                'tax' => 0,
                'discount' => 500,
                'items' => [
                    [
                        'name' => '新項目',
                        'unit' => '式',
                        'quantity' => 3,
                        'unit_price' => 2000,
                    ],
                ],
            ])
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();

        $this->assertSame('draft', $quotation->status);
        $this->assertSame(6000, $quotation->subtotal);
        $this->assertSame(5500, $quotation->total);
        $this->assertSame(1, $quotation->items()->count());
        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $quotation->id,
            'name' => '新項目',
            'subtotal' => 6000,
        ]);
    }

    public function test_authenticated_user_can_delete_quotation(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '可刪報價客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-0003',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->delete(route('quotations.destroy', $quotation))
            ->assertRedirect(route('quotations.index'));

        $this->assertDatabaseMissing('quotations', [
            'id' => $quotation->id,
        ]);
    }

    public function test_draft_quotation_can_be_submitted_for_review(): void
    {
        $user = $this->authorizedUser(roleCode: 'sales');
        $customer = Customer::create(['name' => '送審客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-REVIEW1',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->post(route('quotations.submit-review', $quotation))
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();

        $this->assertSame('reviewing', $quotation->status);
        $this->assertNull($quotation->approved_by);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'submit_review',
            'event' => 'quotation.submitted_for_review',
            'subject_type' => Quotation::class,
            'subject_id' => $quotation->id,
            'module' => 'quotations',
        ]);
    }

    public function test_reviewing_quotation_can_be_approved(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '核准客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-APPROVE1',
            'customer_id' => $customer->id,
            'status' => 'reviewing',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->post(route('quotations.approve', $quotation))
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();

        $this->assertSame('approved', $quotation->status);
        $this->assertSame($user->id, $quotation->approved_by);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'approve',
            'event' => 'quotation.approved',
            'subject_id' => $quotation->id,
            'module' => 'quotations',
        ]);
    }

    public function test_approved_quotation_can_be_sent_and_accepted_by_customer(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '客戶確認流程']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-CUSTOMER1',
            'customer_id' => $customer->id,
            'status' => 'approved',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->post(route('quotations.send-customer', $quotation))
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();

        $this->assertSame('sent', $quotation->status);
        $this->assertSame('pending', $quotation->customer_confirmation_status);
        $this->assertNotNull($quotation->customer_sent_at);

        $this
            ->actingAs($user)
            ->post(route('quotations.accept-customer', $quotation), [
                'customer_confirmed_by_name' => '王先生',
            ])
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();

        $this->assertSame('accepted', $quotation->status);
        $this->assertSame('accepted', $quotation->customer_confirmation_status);
        $this->assertSame('王先生', $quotation->customer_confirmed_by_name);
        $this->assertNotNull($quotation->customer_confirmed_at);
        $this->assertNotNull($quotation->locked_at);
    }

    public function test_quotation_can_be_voided_and_reopened_as_new_draft_version(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '重開版本客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-VOID1',
            'customer_id' => $customer->id,
            'status' => 'sent',
            'subtotal' => 1000,
            'total' => 1000,
        ]);
        $quotation->items()->create([
            'name' => '原報價項目',
            'unit' => '式',
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->post(route('quotations.void', $quotation), [
                'void_reason' => '客戶要求改版',
            ])
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();

        $this->assertSame('voided', $quotation->status);
        $this->assertSame('客戶要求改版', $quotation->void_reason);
        $this->assertNotNull($quotation->voided_at);

        $this
            ->actingAs($user)
            ->post(route('quotations.reopen', $quotation))
            ->assertRedirect();

        $quotation->refresh();
        $newQuotation = Quotation::where('reopened_from_id', $quotation->id)->firstOrFail();

        $this->assertSame('draft', $newQuotation->status);
        $this->assertSame('not_sent', $newQuotation->customer_confirmation_status);
        $this->assertSame($newQuotation->id, $quotation->superseded_by_id);
        $this->assertSame(1, $newQuotation->items()->count());
    }

    public function test_quotation_accepts_confirmation_attachments(): void
    {
        Storage::fake('public');

        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '附件客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-ATTACH1',
            'customer_id' => $customer->id,
            'status' => 'accepted',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->post(route('quotations.attachments.store', $quotation), [
                'file' => UploadedFile::fake()->create('signed-contract.pdf', 100, 'application/pdf'),
                'description' => '客戶簽回',
            ])
            ->assertRedirect(route('quotations.show', $quotation));

        $attachment = DocumentAttachment::firstOrFail();

        $this->assertSame(Quotation::class, $attachment->attachable_type);
        $this->assertSame($quotation->id, $attachment->attachable_id);
        $this->assertSame('客戶簽回', $attachment->description);
        Storage::disk('public')->assertExists($attachment->file_path);
    }

    public function test_quotation_attachment_rejects_executable_files(): void
    {
        Storage::fake('public');

        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '惡意附件客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-ATTACH2',
            'customer_id' => $customer->id,
            'status' => 'accepted',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->from(route('quotations.show', $quotation))
            ->post(route('quotations.attachments.store', $quotation), [
                'file' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php'),
            ])
            ->assertRedirect(route('quotations.show', $quotation))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('document_attachments', 0);
    }

    public function test_quotation_attachment_rejects_disallowed_file_extensions(): void
    {
        Storage::fake('public');

        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '副檔名白名單客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-ATTACH3',
            'customer_id' => $customer->id,
            'status' => 'accepted',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        foreach ([
            ['payload.html', 'text/html'],
            ['payload.svg', 'image/svg+xml'],
            ['payload.php', 'application/x-php'],
            ['payload.phar', 'application/octet-stream'],
            ['payload.phtml', 'application/x-php'],
            ['payload.js', 'application/javascript'],
            ['payload.zip', 'application/zip'],
        ] as [$name, $mime]) {
            $this
                ->actingAs($user)
                ->from(route('quotations.show', $quotation))
                ->post(route('quotations.attachments.store', $quotation), [
                    'file' => UploadedFile::fake()->create($name, 1, $mime),
                ])
                ->assertRedirect(route('quotations.show', $quotation))
                ->assertSessionHasErrors('file');
        }

        $this->assertDatabaseCount('document_attachments', 0);
    }

    public function test_quotation_attachment_rejects_mime_spoofing(): void
    {
        Storage::fake('public');

        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => 'MIME 偽造客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-ATTACH4',
            'customer_id' => $customer->id,
            'status' => 'accepted',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->from(route('quotations.show', $quotation))
            ->post(route('quotations.attachments.store', $quotation), [
                'file' => UploadedFile::fake()->create('spoofed.jpg', 1, 'text/html'),
            ])
            ->assertRedirect(route('quotations.show', $quotation))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('document_attachments', 0);
    }

    public function test_quotation_attachment_allows_business_document_and_image_types(): void
    {
        Storage::fake('public');

        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '允許附件客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-ATTACH5',
            'customer_id' => $customer->id,
            'status' => 'accepted',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        foreach ([
            ['signed.pdf', 'application/pdf'],
            ['photo.jpg', 'image/jpeg'],
            ['photo.png', 'image/png'],
            ['photo.webp', 'image/webp'],
            ['contract.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['estimate.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ] as [$name, $mime]) {
            $this
                ->actingAs($user)
                ->post(route('quotations.attachments.store', $quotation), [
                    'file' => UploadedFile::fake()->create($name, 1, $mime),
                ])
                ->assertRedirect(route('quotations.show', $quotation))
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('document_attachments', 6);
        DocumentAttachment::all()->each(
            fn (DocumentAttachment $attachment) => Storage::disk('public')->assertExists($attachment->file_path),
        );
    }

    public function test_reviewing_quotation_can_be_rejected_to_draft(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '退回客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-REJECT1',
            'customer_id' => $customer->id,
            'approved_by' => $user->id,
            'status' => 'reviewing',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->post(route('quotations.reject', $quotation))
            ->assertRedirect(route('quotations.show', $quotation));

        $quotation->refresh();

        $this->assertSame('draft', $quotation->status);
        $this->assertNull($quotation->approved_by);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'reject',
            'event' => 'quotation.rejected',
            'subject_id' => $quotation->id,
            'module' => 'quotations',
        ]);
    }

    public function test_unapproved_quotation_cannot_be_converted_to_project(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '未核准轉案客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-CONVERT0',
            'customer_id' => $customer->id,
            'status' => 'reviewing',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->post(route('quotations.convert-project', $quotation))
            ->assertStatus(422);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_customer_accepted_quotation_can_be_converted_to_project(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '轉案客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-CONVERT1',
            'customer_id' => $customer->id,
            'status' => 'accepted',
            'customer_confirmation_status' => 'accepted',
            'customer_confirmed_at' => now(),
            'locked_at' => now(),
            'subtotal' => 3000,
            'total' => 3300,
        ]);
        $quotation->items()->create([
            'name' => 'C 型鋼',
            'unit' => '支',
            'quantity' => 2,
            'unit_price' => 1500,
            'cost_price' => 1000,
            'subtotal' => 3000,
        ]);

        $this
            ->actingAs($user)
            ->post(route('quotations.convert-project', $quotation))
            ->assertRedirect();

        $quotation->refresh();
        $project = Project::firstOrFail();

        $this->assertSame($project->id, $quotation->project_id);
        $this->assertSame('contracted', $project->status);
        $this->assertSame('轉案客戶 - Q-2026-CONVERT1', $project->name);
        $this->assertSame(3300, $project->contract_amount);
        $this->assertSame(2000, $project->estimated_cost);
        $this->assertSame(1300, $project->gross_profit);
        $this->assertSame($quotation->id, $project->metadata['source_quotation_id']);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'convert_project',
            'event' => 'quotation.converted_to_project',
            'subject_id' => $quotation->id,
            'module' => 'quotations',
        ]);
    }

    public function test_bound_quotation_is_not_converted_twice(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '已轉案客戶']);
        $project = Project::create([
            'project_no' => 'TPH-2026-9001',
            'customer_id' => $customer->id,
            'name' => '既有工程',
            'status' => 'contracted',
        ]);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-CONVERT2',
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'status' => 'approved',
            'subtotal' => 3000,
            'total' => 3000,
        ]);

        $this
            ->actingAs($user)
            ->post(route('quotations.convert-project', $quotation))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseCount('projects', 1);
    }

    public function test_user_without_capability_cannot_approve_quotation(): void
    {
        $user = $this->authorizedUser(roleCode: 'sales');
        $customer = Customer::create(['name' => '無核准權限客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-FORBID1',
            'customer_id' => $customer->id,
            'status' => 'reviewing',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->post(route('quotations.approve', $quotation))
            ->assertForbidden();
    }

    public function test_reviewing_quotation_cannot_be_edited(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '審核中客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-LOCK1',
            'customer_id' => $customer->id,
            'status' => 'reviewing',
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->get(route('quotations.edit', $quotation))
            ->assertForbidden();
    }

    public function test_quotation_pdf_view_contains_formal_quotation_content(): void
    {
        $customer = Customer::create([
            'name' => 'PDF 客戶',
            'phone' => '02-1111-2222',
            'tax_id' => '12345678',
        ]);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-PDF1',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'subtotal' => 2200,
            'tax' => 110,
            'discount' => 0,
            'total' => 2310,
        ]);
        $quotation->items()->create([
            'name' => 'C 型鋼',
            'spec' => '100x50x20x2.3mm',
            'unit' => '支',
            'quantity' => 2,
            'unit_price' => 1100,
            'subtotal' => 2200,
        ]);

        $html = view('pdf.quotation', [
            'quotation' => $quotation->load(['customer', 'project', 'creator', 'items']),
            'statuses' => ['draft' => '草稿'],
            'settings' => [
                'company' => [
                    'name' => '鼎盛鐵皮工程',
                    'phone' => '02-1234-5678',
                    'address' => '台北市信義區',
                    'tax_id' => '12345678',
                ],
                'quotation' => [
                    'default_terms' => "本報價有效期限為 14 天。\n追加工程另行報價。",
                ],
            ],
        ])->render();

        $this->assertStringContainsString('報價單', $html);
        $this->assertStringContainsString('Q-2026-PDF1', $html);
        $this->assertStringContainsString('PDF 客戶', $html);
        $this->assertStringContainsString('C 型鋼', $html);
        $this->assertStringContainsString('NT$ 2,310', $html);
        $this->assertStringContainsString('鼎盛鐵皮工程', $html);
        $this->assertStringContainsString('02-1234-5678', $html);
        $this->assertStringContainsString('台北市信義區', $html);
        $this->assertStringContainsString('報價條款', $html);
        $this->assertStringContainsString('本報價有效期限為 14 天。', $html);
        $this->assertStringContainsString('追加工程另行報價。', $html);
    }

    public function test_authenticated_user_can_preview_quotation_pdf(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '下載 PDF 客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-PDF2',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'subtotal' => 1000,
            'total' => 1000,
        ]);
        $quotation->items()->create([
            'name' => '自訂施工',
            'unit' => '式',
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
        ]);

        $this
            ->actingAs($user)
            ->get(route('quotations.pdf', $quotation))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="Q-2026-PDF2.pdf"');

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'export_pdf',
            'event' => 'quotation.pdf_exported',
            'subject_type' => Quotation::class,
            'subject_id' => $quotation->id,
            'module' => 'quotations',
        ]);
        $this->assertDatabaseHas('document_versions', [
            'document_type' => Quotation::class,
            'document_id' => $quotation->id,
            'category' => 'quotation_pdf',
            'version_number' => 1,
            'status' => 'active',
        ]);
    }
}
