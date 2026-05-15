<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentTransaction;
use App\Models\FinancialRecord;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProgressLog;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\QuotationTemplate;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkCrew;
use App\Models\Worker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        $siteLead = User::updateOrCreate(
            ['email' => 'foreman@example.com'],
            [
                'name' => '林志明',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ],
        );

        $fieldRole = Role::where('code', 'field_lead')->first();
        if ($fieldRole) {
            $siteLead->roles()->syncWithoutDetaching([$fieldRole->id]);
        }

        $customer = $this->seedCustomer();
        $crew = $this->seedWorkCrew($siteLead);
        $materials = $this->seedMaterials();
        $this->call(QuotationTemplateSeeder::class);

        $template = QuotationTemplate::where('name', '一般鐵皮屋')->first();
        $project = $this->seedProject($customer, $crew, $admin);
        $quotation = $this->seedQuotation($customer, $project, $template, $materials, $admin);

        $this->seedPurchaseOrder($materials, $admin);
        $dispatch = $this->seedDispatch($project, $crew, $admin);
        $this->seedFieldExecution($project, $dispatch, $crew, $admin);
        $changeOrder = $this->seedChangeOrder($project, $quotation, $admin);
        $this->seedFinancialRecords($project, $changeOrder);
        $this->seedEquipmentFlow($project, $crew, $admin);
    }

    private function seedCustomer(): Customer
    {
        $customer = Customer::updateOrCreate(
            ['name' => 'Demo 客戶｜新北鋼構食品廠'],
            [
                'phone' => '02-2999-0101',
                'line_id' => 'tinhouse-demo',
                'tax_id' => '24567890',
                'source' => '官網詢價',
                'address' => '新北市五股區成泰路三段 188 號',
                'note' => 'Demo 完整流程客戶：詢價、報價、施工、請款。',
            ],
        );

        $customer->contacts()->delete();
        $customer->contacts()->createMany([
            [
                'name' => '陳美玲',
                'title' => '廠務經理',
                'phone' => '0912-345-678',
                'email' => 'meiling.chen@example.com',
                'line_id' => 'meiling-demo',
                'is_primary' => true,
                'note' => '主要窗口，負責現場協調與付款確認。',
            ],
            [
                'name' => '黃主任',
                'title' => '工務',
                'phone' => '0922-111-333',
                'email' => 'site.demo@example.com',
                'line_id' => 'site-demo',
                'is_primary' => false,
                'note' => '協助丈量與驗收。',
            ],
        ]);

        return $customer;
    }

    private function seedWorkCrew(User $siteLead): WorkCrew
    {
        $crew = WorkCrew::updateOrCreate(
            ['name' => 'Demo 北區鋼構班'],
            [
                'leader_name' => '林志明',
                'phone' => '0933-456-789',
                'specialties' => ['鋼構', '鐵皮屋', '浪板安裝', '現場焊接'],
                'daily_rate' => 15000,
                'note' => 'Demo 主施工班底。',
            ],
        );

        $workers = [
            [
                'name' => '林志明',
                'user_id' => $siteLead->id,
                'phone' => '0933-456-789',
                'role' => '工地主任',
                'daily_rate' => 3500,
                'certifications' => ['乙級職安', '高空作業'],
                'insurance_expires_at' => now()->addYear()->toDateString(),
                'is_active' => true,
                'note' => 'Demo 現場負責人。',
            ],
            [
                'name' => '王建宏',
                'user_id' => null,
                'phone' => '0933-111-222',
                'role' => '焊接師傅',
                'daily_rate' => 3200,
                'certifications' => ['焊接'],
                'insurance_expires_at' => now()->addMonths(10)->toDateString(),
                'is_active' => true,
                'note' => '負責 H 鋼與 C 型鋼焊接。',
            ],
            [
                'name' => '張家豪',
                'user_id' => null,
                'phone' => '0933-333-555',
                'role' => '浪板技師',
                'daily_rate' => 3000,
                'certifications' => ['高空作業'],
                'insurance_expires_at' => now()->addMonths(8)->toDateString(),
                'is_active' => true,
                'note' => '負責屋面浪板與收邊。',
            ],
        ];

        foreach ($workers as $worker) {
            Worker::updateOrCreate(
                ['name' => $worker['name'], 'work_crew_id' => $crew->id],
                $worker + ['work_crew_id' => $crew->id],
            );
        }

        return $crew;
    }

    /**
     * @return array<string, Material>
     */
    private function seedMaterials(): array
    {
        $steel = MaterialCategory::updateOrCreate(
            ['code' => 'DEMO-STEEL'],
            ['name' => 'Demo 鋼材', 'description' => 'Demo 流程用鋼構材料。'],
        );
        $panel = MaterialCategory::updateOrCreate(
            ['code' => 'DEMO-PANEL'],
            ['name' => 'Demo 板材與五金', 'description' => 'Demo 流程用屋面與五金材料。'],
        );

        $rows = [
            'h_beam' => [
                'material_category_id' => $steel->id,
                'name' => 'Demo H 鋼',
                'spec' => '200x100x5.5x8mm / 6m',
                'unit' => '支',
                'length' => 6,
                'weight' => 124.8,
                'cost_price' => 4300,
                'sale_price' => 5800,
                'safe_stock' => 10,
                'current_stock' => 18,
                'metadata' => ['demo' => true],
            ],
            'c_steel' => [
                'material_category_id' => $steel->id,
                'name' => 'Demo C 型鋼',
                'spec' => 'C100x50x20x2.3mm / 6m',
                'unit' => '支',
                'length' => 6,
                'weight' => 22.5,
                'cost_price' => 850,
                'sale_price' => 1100,
                'safe_stock' => 30,
                'current_stock' => 80,
                'metadata' => ['demo' => true],
            ],
            'roof_panel' => [
                'material_category_id' => $panel->id,
                'name' => 'Demo 烤漆浪板',
                'spec' => '0.5mm 灰白 6m',
                'unit' => '片',
                'length' => 6,
                'width' => 0.76,
                'thickness' => 0.5,
                'cost_price' => 650,
                'sale_price' => 900,
                'safe_stock' => 50,
                'current_stock' => 120,
                'metadata' => ['demo' => true],
            ],
            'hardware' => [
                'material_category_id' => $panel->id,
                'name' => 'Demo 螺絲與五金',
                'spec' => '自攻螺絲、防水墊片、矽利康',
                'unit' => '式',
                'cost_price' => 3500,
                'sale_price' => 5000,
                'safe_stock' => 5,
                'current_stock' => 12,
                'metadata' => ['demo' => true],
            ],
        ];

        return collect($rows)
            ->mapWithKeys(fn (array $row, string $key) => [
                $key => Material::updateOrCreate(
                    ['name' => $row['name']],
                    $row,
                ),
            ])
            ->all();
    }

    private function seedProject(Customer $customer, WorkCrew $crew, User $admin): Project
    {
        return Project::updateOrCreate(
            ['project_no' => 'DEMO-2026-0001'],
            [
                'customer_id' => $customer->id,
                'manager_id' => $admin->id,
                'work_crew_id' => $crew->id,
                'name' => 'Demo 五股食品廠屋頂鋼構翻修',
                'type' => '鐵皮屋工程',
                'status' => 'in_progress',
                'address' => '新北市五股區成泰路三段 188 號',
                'latitude' => 25.0912345,
                'longitude' => 121.4387654,
                'start_date' => now()->subDays(5)->toDateString(),
                'end_date' => now()->addDays(15)->toDateString(),
                'contract_amount' => 668000,
                'estimated_cost' => 468000,
                'actual_cost' => 215000,
                'gross_profit' => 453000,
                'metadata' => [
                    'demo' => true,
                    'flow' => 'lead_to_cash',
                    'steps' => ['customer', 'quotation', 'project', 'purchase', 'dispatch', 'progress', 'billing'],
                ],
            ],
        );
    }

    /**
     * @param  array<string, Material>  $materials
     */
    private function seedQuotation(Customer $customer, Project $project, ?QuotationTemplate $template, array $materials, User $admin): Quotation
    {
        $quotation = Quotation::updateOrCreate(
            ['quotation_no' => 'DEMO-Q-2026-0001'],
            [
                'customer_id' => $customer->id,
                'project_id' => $project->id,
                'quotation_template_id' => $template?->id,
                'created_by' => $admin->id,
                'approved_by' => $admin->id,
                'status' => 'approved',
                'subtotal' => 668000,
                'tax' => 0,
                'discount' => 0,
                'total' => 668000,
                'profit_rate' => 29.94,
                'valid_until' => now()->addDays(20)->toDateString(),
                'items_json' => null,
                'template_inputs' => [
                    'length' => 18,
                    'width' => 12,
                    'spacing' => 1.2,
                    'panel_effective_width' => 0.76,
                    'panel_length' => 6,
                    'piece_length' => 6,
                ],
                'note' => 'Demo 已核准報價，已轉工程案件。',
            ],
        );

        $quotation->items()->delete();
        $quotation->items()->createMany([
            $this->quotationItem($materials['h_beam'], 18, 5800, 4300, 104400),
            $this->quotationItem($materials['c_steel'], 64, 1100, 850, 70400),
            $this->quotationItem($materials['roof_panel'], 72, 900, 650, 64800),
            $this->quotationItem($materials['hardware'], 1, 5000, 3500, 5000),
            [
                'name' => 'Demo 施工工資與吊車',
                'spec' => '鋼構拆除、吊掛、安裝、收邊',
                'unit' => '式',
                'quantity' => 1,
                'unit_price' => 423400,
                'cost_price' => 290000,
                'waste_rate' => 0,
                'subtotal' => 423400,
                'note' => '含安全防護與現場清運。',
            ],
        ]);

        return $quotation;
    }

    /**
     * @return array<string, mixed>
     */
    private function quotationItem(Material $material, float $quantity, int $unitPrice, int $costPrice, int $subtotal): array
    {
        return [
            'material_id' => $material->id,
            'name' => $material->name,
            'spec' => $material->spec,
            'unit' => $material->unit,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_price' => $costPrice,
            'waste_rate' => 5,
            'subtotal' => $subtotal,
            'note' => null,
        ];
    }

    /**
     * @param  array<string, Material>  $materials
     */
    private function seedPurchaseOrder(array $materials, User $admin): void
    {
        $supplier = Supplier::updateOrCreate(
            ['name' => 'Demo 大同鋼鐵材料行'],
            [
                'contact_name' => '許小姐',
                'phone' => '02-2555-8899',
                'email' => 'sales@demo-steel.example',
                'tax_id' => '53210001',
                'address' => '新北市三重區重新路五段 66 號',
                'payment_terms' => '月結 30 天',
                'is_active' => true,
                'note' => 'Demo 鋼材與板材供應商。',
            ],
        );

        $order = PurchaseOrder::updateOrCreate(
            ['purchase_order_no' => 'DEMO-PO-2026-0001'],
            [
                'supplier_id' => $supplier->id,
                'created_by' => $admin->id,
                'status' => 'partially_received',
                'ordered_date' => now()->subDays(9)->toDateString(),
                'expected_date' => now()->addDays(3)->toDateString(),
                'subtotal' => 175700,
                'tax' => 0,
                'discount' => 0,
                'total' => 175700,
                'note' => 'Demo 採購單，部分材料已到貨入庫。',
            ],
        );

        InventoryTransaction::where('reference_no', $order->purchase_order_no)
            ->where('type', 'purchase_in')
            ->delete();
        $order->items()->delete();
        $items = [
            [$materials['h_beam'], 12, 4300, 51600, 8],
            [$materials['c_steel'], 60, 850, 51000, 60],
            [$materials['roof_panel'], 90, 650, 58500, 45],
            [$materials['hardware'], 4, 3650, 14600, 4],
        ];

        foreach ($items as [$material, $quantity, $unitCost, $subtotal, $receivedQuantity]) {
            $item = $order->items()->create([
                'material_id' => $material->id,
                'name' => $material->name,
                'spec' => $material->spec,
                'unit' => $material->unit,
                'quantity' => $quantity,
                'received_quantity' => $receivedQuantity,
                'unit_cost' => $unitCost,
                'subtotal' => $subtotal,
                'note' => null,
            ]);

            if ($receivedQuantity > 0) {
                InventoryTransaction::updateOrCreate(
                    [
                        'purchase_order_item_id' => $item->id,
                        'type' => 'purchase_in',
                    ],
                    [
                        'material_id' => $material->id,
                        'project_id' => null,
                        'created_by' => $admin->id,
                        'quantity' => $receivedQuantity,
                        'unit' => $material->unit,
                        'unit_cost' => $unitCost,
                        'total_cost' => $receivedQuantity * $unitCost,
                        'reference_no' => $order->purchase_order_no,
                        'note' => 'Demo 採購到貨入庫。',
                        'occurred_at' => now()->subDays(3),
                    ],
                );
            }
        }
    }

    private function seedDispatch(Project $project, WorkCrew $crew, User $admin): Dispatch
    {
        $dispatch = Dispatch::updateOrCreate(
            [
                'project_id' => $project->id,
                'work_item' => 'Demo 第一階段鋼構與屋面施工',
                'scheduled_date' => now()->subDays(2)->toDateString(),
            ],
            [
                'work_crew_id' => $crew->id,
                'created_by' => $admin->id,
                'status' => 'in_progress',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'address' => $project->address,
                'instructions' => '先完成舊屋面拆除、主樑補強與 A 區浪板安裝。',
            ],
        );

        $dispatch->workers()->sync(
            $crew->workers->mapWithKeys(fn (Worker $worker) => [
                $worker->id => [
                    'hours' => 8,
                    'wage' => $worker->daily_rate,
                    'note' => $worker->role,
                ],
            ])->all(),
        );

        return $dispatch;
    }

    private function seedFieldExecution(Project $project, Dispatch $dispatch, WorkCrew $crew, User $admin): void
    {
        $lead = $crew->workers()->where('name', '林志明')->first();

        $log = ProgressLog::updateOrCreate(
            [
                'project_id' => $project->id,
                'dispatch_id' => $dispatch->id,
                'work_date' => now()->subDays(2)->toDateString(),
            ],
            [
                'worker_id' => $lead?->id,
                'created_by' => $admin->id,
                'weather' => '晴',
                'worker_count' => 3,
                'progress_percent' => 45,
                'work_items' => '完成舊浪板拆除、主樑補強、A 區浪板安裝。',
                'description' => '上午完成安全母索與拆除作業，下午開始 A 區屋面浪板鋪設。',
                'issue' => 'B 區女兒牆收邊需追加防水封板。',
                'voice_text' => '現場回報：A 區進度正常，B 區需要追加封板。',
                'latitude' => $project->latitude,
                'longitude' => $project->longitude,
                'note' => 'Demo 工程日誌。',
            ],
        );

        $log->photos()->delete();
        $progressPhotoPath = 'progress-photos/demo/site-before.png';
        $this->storeDemoImage($progressPhotoPath);

        $log->photos()->create([
            'project_id' => $project->id,
            'dispatch_id' => $dispatch->id,
            'uploaded_by' => $admin->id,
            'file_path' => $progressPhotoPath,
            'original_name' => 'site-before.png',
            'mime_type' => 'image/png',
            'size' => 128000,
            'caption' => 'Demo 施工前屋面狀況',
            'taken_at' => now()->subDays(2)->setTime(8, 30),
            'latitude' => $project->latitude,
            'longitude' => $project->longitude,
            'watermark_text' => $project->name.' / '.$dispatch->work_item.' / '.now()->subDays(2)->toDateString(),
        ]);

        AttendanceRecord::where('dispatch_id', $dispatch->id)->delete();
        foreach ($crew->workers as $worker) {
            $clockInPath = 'attendance-photos/demo/'.$worker->id.'-in.png';
            $clockOutPath = 'attendance-photos/demo/'.$worker->id.'-out.png';
            $this->storeDemoImage($clockInPath);
            $this->storeDemoImage($clockOutPath);

            AttendanceRecord::create([
                'dispatch_id' => $dispatch->id,
                'project_id' => $project->id,
                'worker_id' => $worker->id,
                'user_id' => $worker->user_id,
                'type' => 'clock_in',
                'worked_minutes' => null,
                'recorded_at' => now()->subDays(2)->setTime(7, 55),
                'latitude' => $project->latitude,
                'longitude' => $project->longitude,
                'distance_meters' => 18,
                'is_within_range' => true,
                'is_duplicate' => false,
                'requires_attention' => false,
                'photo_path' => $clockInPath,
                'note' => 'Demo 上工打卡。',
            ]);
            AttendanceRecord::create([
                'dispatch_id' => $dispatch->id,
                'project_id' => $project->id,
                'worker_id' => $worker->id,
                'user_id' => $worker->user_id,
                'type' => 'clock_out',
                'worked_minutes' => 480,
                'recorded_at' => now()->subDays(2)->setTime(17, 12),
                'latitude' => $project->latitude,
                'longitude' => $project->longitude,
                'distance_meters' => 25,
                'is_within_range' => true,
                'is_duplicate' => false,
                'requires_attention' => false,
                'photo_path' => $clockOutPath,
                'note' => 'Demo 下工打卡。',
            ]);
        }

        InventoryTransaction::where('project_id', $project->id)
            ->where('reference_no', 'DEMO-2026-0001')
            ->delete();

        foreach ([
            ['Demo C 型鋼', 24, 850],
            ['Demo 烤漆浪板', 30, 650],
            ['Demo 螺絲與五金', 1, 3500],
        ] as [$materialName, $quantity, $unitCost]) {
            $material = Material::where('name', $materialName)->first();
            if (! $material) {
                continue;
            }

            InventoryTransaction::create([
                'material_id' => $material->id,
                'project_id' => $project->id,
                'created_by' => $admin->id,
                'type' => 'outbound',
                'quantity' => $quantity,
                'unit' => $material->unit,
                'unit_cost' => $unitCost,
                'total_cost' => $quantity * $unitCost,
                'reference_no' => 'DEMO-2026-0001',
                'note' => 'Demo 工程領料。',
                'occurred_at' => now()->subDays(2)->setTime(9, 0),
            ]);
        }
    }

    private function seedChangeOrder(Project $project, Quotation $quotation, User $admin): ProjectChangeOrder
    {
        $order = ProjectChangeOrder::updateOrCreate(
            ['project_id' => $project->id, 'title' => 'Demo B 區女兒牆防水封板追加'],
            [
                'financial_record_id' => null,
                'quotation_id' => $quotation->id,
                'created_by' => $admin->id,
                'approved_by' => $admin->id,
                'description' => '現場發現 B 區女兒牆既有收邊老化，追加防水封板與矽利康收邊。',
                'amount' => 42000,
                'requires_formal_quotation' => true,
                'requested_date' => now()->subDay()->toDateString(),
                'submitted_at' => now()->subDay()->setTime(10, 0),
                'approved_date' => now()->toDateString(),
                'approved_at' => now()->setTime(9, 30),
                'customer_confirmed_at' => now()->setTime(11, 15),
                'due_date' => now()->addDays(14)->toDateString(),
                'status' => 'customer_confirmed',
                'customer_note' => '客戶已於 LINE 確認追加。',
                'internal_note' => 'Demo 追加單，尚未轉追加款。',
                'converted_at' => null,
                'metadata' => ['demo' => true],
            ],
        );

        return $order;
    }

    private function seedFinancialRecords(Project $project, ProjectChangeOrder $changeOrder): void
    {
        FinancialRecord::where('project_id', $project->id)->delete();

        FinancialRecord::create([
            'project_id' => $project->id,
            'type' => 'deposit',
            'title' => 'Demo 簽約訂金 30%',
            'amount' => 200400,
            'due_date' => now()->subDays(12)->toDateString(),
            'paid_date' => now()->subDays(10)->toDateString(),
            'status' => 'paid',
            'note' => 'Demo 已收訂金。',
        ]);

        FinancialRecord::create([
            'project_id' => $project->id,
            'type' => 'progress',
            'title' => 'Demo 進場款 40%',
            'amount' => 267200,
            'due_date' => now()->addDays(3)->toDateString(),
            'paid_date' => null,
            'status' => 'pending',
            'note' => 'Demo 待收進場款，可用於請款單 PDF。',
        ]);

        $record = FinancialRecord::create([
            'project_id' => $project->id,
            'project_change_order_id' => $changeOrder->id,
            'type' => 'change_order',
            'title' => 'Demo B 區防水封板追加款',
            'amount' => $changeOrder->amount,
            'due_date' => now()->addDays(14)->toDateString(),
            'paid_date' => null,
            'status' => 'pending',
            'note' => 'Demo 追加工程款。',
        ]);

        $changeOrder->update([
            'financial_record_id' => $record->id,
            'status' => 'converted',
            'converted_at' => now(),
        ]);
    }

    private function seedEquipmentFlow(Project $project, WorkCrew $crew, User $admin): void
    {
        $category = EquipmentCategory::updateOrCreate(
            ['code' => 'DEMO-LIFT'],
            [
                'name' => 'Demo 吊掛與高空設備',
                'description' => 'Demo 工程用機具。',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $equipment = Equipment::updateOrCreate(
            ['equipment_no' => 'DEMO-EQ-0001'],
            [
                'equipment_category_id' => $category->id,
                'current_project_id' => $project->id,
                'current_worker_id' => null,
                'current_work_crew_id' => $crew->id,
                'name' => 'Demo 14 米剪刀車',
                'brand' => 'Genie',
                'model' => 'GS-4390',
                'serial_no' => 'DEMO-GS4390-001',
                'asset_tag' => 'DEMO-LIFT-001',
                'status' => 'assigned',
                'condition' => 'good',
                'purchase_date' => now()->subYears(2)->toDateString(),
                'purchase_price' => 680000,
                'warranty_until' => now()->addMonths(6)->toDateString(),
                'last_maintenance_at' => now()->subMonth(),
                'next_maintenance_at' => now()->addMonth(),
                'note' => 'Demo 已指派到工程現場。',
                'metadata' => ['demo' => true],
            ],
        );

        $equipment->transactions()->delete();
        $equipmentPhotoPath = 'equipment-photos/demo/lift-checkout.png';
        $this->storeDemoImage($equipmentPhotoPath);

        EquipmentTransaction::create([
            'equipment_id' => $equipment->id,
            'project_id' => $project->id,
            'worker_id' => null,
            'work_crew_id' => $crew->id,
            'handled_by' => $admin->id,
            'type' => 'assign_project',
            'occurred_at' => now()->subDays(3)->setTime(15, 0),
            'due_at' => now()->addDays(12)->setTime(17, 0),
            'condition_before' => 'good',
            'condition_after' => 'good',
            'from_location' => '公司倉庫',
            'to_location' => $project->address,
            'photo_path' => $equipmentPhotoPath,
            'note' => 'Demo 機具派發到工程現場。',
        ]);
    }

    private function storeDemoImage(string $path): void
    {
        Storage::disk('public')->put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
        ));
    }
}
