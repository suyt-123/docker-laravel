<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Material;
use App\Models\Quotation;
use App\Models\QuotationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_template_pages(): void
    {
        $this->get(route('quotation-templates.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_create_update_and_delete_template(): void
    {
        $user = $this->authorizedUser(roleCode: 'office');

        $this->actingAs($user)->post(route('quotation-templates.store'), $this->templatePayload([
            'name' => '一般鐵皮屋',
        ]))->assertRedirect();

        $template = QuotationTemplate::where('name', '一般鐵皮屋')->firstOrFail();
        $this->assertSame(1, $template->items()->count());

        $this->actingAs($user)->patch(route('quotation-templates.update', $template), $this->templatePayload([
            'name' => '一般鐵皮屋 V2',
            'status' => 'inactive',
        ]))->assertRedirect(route('quotation-templates.show', $template));

        $template->refresh();
        $this->assertSame('一般鐵皮屋 V2', $template->name);
        $this->assertSame('inactive', $template->status);

        $this->actingAs($user)
            ->delete(route('quotation-templates.destroy', $template))
            ->assertRedirect(route('quotation-templates.index'));

        $this->assertDatabaseMissing('quotation_templates', ['id' => $template->id]);
    }

    public function test_sales_can_view_active_templates_but_cannot_manage_them(): void
    {
        $user = $this->authorizedUser(roleCode: 'sales');
        QuotationTemplate::create([
            'name' => '啟用模板',
            'status' => 'active',
        ])->items()->create($this->itemPayload());
        QuotationTemplate::create([
            'name' => '停用模板',
            'status' => 'inactive',
        ])->items()->create($this->itemPayload());

        $this->actingAs($user)
            ->get(route('quotation-templates.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('QuotationTemplates/Index')
                ->has('templates.data', 1)
                ->where('templates.data.0.name', '啟用模板')
            );

        $this->actingAs($user)
            ->get(route('quotation-templates.create'))
            ->assertForbidden();
    }

    public function test_template_calculation_uses_builtin_formulas(): void
    {
        $user = $this->authorizedUser(roleCode: 'sales');
        $template = QuotationTemplate::create([
            'name' => '算料模板',
            'status' => 'active',
            'parameter_definitions' => [
                ['key' => 'length', 'label' => '長度', 'default' => 10],
                ['key' => 'width', 'label' => '寬度', 'default' => 6],
                ['key' => 'spacing', 'label' => '間距', 'default' => 2],
                ['key' => 'panel_effective_width', 'label' => '有效寬度', 'default' => 1],
                ['key' => 'panel_length', 'label' => '板長', 'default' => 3],
                ['key' => 'piece_length', 'label' => '支長', 'default' => 5],
            ],
        ]);
        foreach ([
            ['name' => '固定', 'formula_type' => 'fixed_quantity', 'formula_params' => ['quantity' => 2], 'waste_rate' => 0],
            ['name' => '面積', 'formula_type' => 'area_based', 'formula_params' => [], 'waste_rate' => 10],
            ['name' => '長度', 'formula_type' => 'length_based', 'formula_params' => [], 'waste_rate' => 0],
            ['name' => '板材', 'formula_type' => 'panel_count', 'formula_params' => [], 'waste_rate' => 0],
            ['name' => '周長', 'formula_type' => 'perimeter_based', 'formula_params' => [], 'waste_rate' => 0],
        ] as $item) {
            $template->items()->create($this->itemPayload($item));
        }

        $this->actingAs($user)
            ->postJson(route('quotation-templates.calculate', $template), [
                'inputs' => [
                    'length' => 10,
                    'width' => 6,
                    'spacing' => 2,
                    'panel_effective_width' => 1,
                    'panel_length' => 3,
                    'piece_length' => 5,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('items.1.quantity', 66)
            ->assertJsonPath('items.2.quantity', 5)
            ->assertJsonPath('items.3.quantity', 20)
            ->assertJsonPath('items.4.quantity', 6.4);
    }

    public function test_inactive_template_cannot_be_calculated_or_applied_to_quotation(): void
    {
        $user = $this->authorizedUser(roleCode: 'sales');
        $customer = Customer::create(['name' => '模板客戶']);
        $template = QuotationTemplate::create([
            'name' => '停用模板',
            'status' => 'inactive',
        ]);
        $template->items()->create($this->itemPayload());

        $this->actingAs($user)
            ->postJson(route('quotation-templates.calculate', $template), ['inputs' => []])
            ->assertStatus(422);

        $this->actingAs($user)
            ->post(route('quotations.store'), [
                'customer_id' => $customer->id,
                'quotation_template_id' => $template->id,
                'status' => 'draft',
                'items' => [
                    [
                        'name' => '測試',
                        'unit' => '式',
                        'quantity' => 1,
                        'unit_price' => 1000,
                    ],
                ],
            ])
            ->assertSessionHasErrors('quotation_template_id');
    }

    public function test_template_can_create_draft_quotation_and_logs_activity(): void
    {
        $user = $this->authorizedUser(roleCode: 'sales');
        $customer = Customer::create(['name' => '模板客戶']);
        $template = QuotationTemplate::create([
            'name' => '鐵皮屋模板',
            'status' => 'active',
            'profit_rate' => 25,
            'parameter_definitions' => [
                ['key' => 'length', 'label' => '長度', 'default' => 10],
                ['key' => 'width', 'label' => '寬度', 'default' => 6],
            ],
        ]);
        $template->items()->create($this->itemPayload([
            'name' => '烤漆浪板',
            'formula_type' => 'area_based',
            'unit_price' => 900,
        ]));

        $this->actingAs($user)
            ->post(route('quotations.store'), [
                'customer_id' => $customer->id,
                'quotation_template_id' => $template->id,
                'template_inputs' => ['length' => 10, 'width' => 6],
                'status' => 'draft',
                'profit_rate' => 25,
                'items' => [
                    [
                        'name' => '烤漆浪板',
                        'unit' => '片',
                        'quantity' => 60,
                        'unit_price' => 900,
                    ],
                ],
            ])
            ->assertRedirect();

        $quotation = Quotation::firstOrFail();

        $this->assertSame('draft', $quotation->status);
        $this->assertSame($template->id, $quotation->quotation_template_id);
        $this->assertSame(54000, $quotation->total);
        $this->assertSame(['length' => 10, 'width' => 6], $quotation->template_inputs);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'action' => 'apply_template',
            'event' => 'quotation_template.applied_to_quotation',
            'subject_type' => Quotation::class,
            'subject_id' => $quotation->id,
            'module' => 'quotations',
        ]);
    }

    private function templatePayload(array $overrides = []): array
    {
        return [
            'name' => '模板',
            'type' => '鐵皮屋',
            'status' => 'active',
            'profit_rate' => 20,
            'tax' => 0,
            'discount' => 0,
            'parameter_definitions' => [
                ['key' => 'length', 'label' => '長度', 'unit' => 'm', 'default' => 10],
                ['key' => 'width', 'label' => '寬度', 'unit' => 'm', 'default' => 6],
            ],
            'items' => [
                $this->itemPayload(),
            ],
            ...$overrides,
        ];
    }

    private function itemPayload(array $overrides = []): array
    {
        return [
            'material_id' => $overrides['material_id'] ?? Material::create([
                'name' => $overrides['name'] ?? 'C 型鋼',
                'unit' => '支',
                'cost_price' => 800,
                'sale_price' => 1000,
            ])->id,
            'name' => 'C 型鋼',
            'spec' => null,
            'unit' => '支',
            'unit_price' => 1000,
            'cost_price' => 800,
            'waste_rate' => 0,
            'formula_type' => 'fixed_quantity',
            'formula_params' => ['quantity' => 1],
            'note' => null,
            'sort_order' => 0,
            ...$overrides,
        ];
    }
}
