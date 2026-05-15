<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dispatch;
use App\Models\FinancialRecord;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Models\WorkCrew;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MvpSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_mvp_models_can_be_persisted_with_core_relationships(): void
    {
        $user = User::factory()->create();

        $customer = Customer::create([
            'name' => '台北鐵皮屋工程行',
            'phone' => '02-2345-6789',
            'line_id' => 'tinhouse-demo',
            'address' => '台北市中山區測試路 1 號',
        ]);

        $customer->contacts()->create([
            'name' => '王先生',
            'phone' => '0912-345-678',
            'is_primary' => true,
        ]);

        $crew = WorkCrew::create([
            'name' => '北區鋼構班',
            'leader_name' => '陳班長',
            'specialties' => ['H 鋼', '烤漆浪板'],
        ]);

        $worker = Worker::create([
            'work_crew_id' => $crew->id,
            'name' => '林師傅',
            'role' => '焊接',
            'certifications' => ['高空作業'],
        ]);

        $project = Project::create([
            'project_no' => 'TPH-2026-0001',
            'customer_id' => $customer->id,
            'manager_id' => $user->id,
            'work_crew_id' => $crew->id,
            'name' => '五股廠房屋頂翻修',
            'type' => '鐵皮屋工程',
            'status' => 'quoted',
            'contract_amount' => 350000,
        ]);

        $category = MaterialCategory::create([
            'name' => 'C 型鋼',
            'code' => 'c-channel',
        ]);

        $material = Material::create([
            'material_category_id' => $category->id,
            'name' => 'C 型鋼',
            'spec' => '100x50x20x2.3mm',
            'unit' => '支',
            'length' => 6,
            'cost_price' => 850,
            'sale_price' => 1100,
            'safe_stock' => 20,
            'current_stock' => 60,
        ]);

        $quotation = Quotation::create([
            'quotation_no' => 'Q-2026-0001',
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'created_by' => $user->id,
            'status' => 'draft',
            'subtotal' => 100000,
            'tax' => 5000,
            'total' => 105000,
            'profit_rate' => 25,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'material_id' => $material->id,
            'name' => 'C 型鋼',
            'spec' => '100x50x20x2.3mm',
            'unit' => '支',
            'quantity' => 10,
            'unit_price' => 1100,
            'cost_price' => 850,
            'subtotal' => 11000,
        ]);

        InventoryTransaction::create([
            'material_id' => $material->id,
            'project_id' => $project->id,
            'created_by' => $user->id,
            'type' => 'outbound',
            'quantity' => 10,
            'unit' => '支',
            'unit_cost' => 850,
            'total_cost' => 8500,
            'occurred_at' => now(),
        ]);

        $dispatch = Dispatch::create([
            'project_id' => $project->id,
            'work_crew_id' => $crew->id,
            'created_by' => $user->id,
            'work_item' => '屋頂骨架施工',
            'status' => 'scheduled',
            'scheduled_date' => now()->toDateString(),
        ]);

        $dispatch->workers()->attach($worker->id, [
            'hours' => 8,
            'wage' => 2800,
        ]);

        FinancialRecord::create([
            'project_id' => $project->id,
            'type' => 'deposit',
            'title' => '訂金',
            'amount' => 105000,
            'status' => 'pending',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $this->assertSame('台北鐵皮屋工程行', $project->customer->name);
        $this->assertSame('北區鋼構班', $project->workCrew->name);
        $this->assertCount(1, $quotation->items);
        $this->assertCount(1, $dispatch->workers);
        $this->assertSame(1, $project->financialRecords()->count());
    }
}
