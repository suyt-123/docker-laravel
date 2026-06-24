<?php

namespace Tests\Feature;

use App\Models\Capability;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_authentication(): void
    {
        $this->getJson(route('api.quotations.index'))->assertUnauthorized();
    }

    public function test_api_requires_capability(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.quotations.index'))->assertForbidden();
    }

    public function test_quotation_api_index_and_show_share_presenter_shape(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['read:quotations']);

        $customer = Customer::create(['name' => 'API 報價客戶']);
        $quotation = Quotation::create([
            'quotation_no' => 'Q-API-0001',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'subtotal' => 1000,
            'total' => 1000,
        ]);
        $quotation->items()->create([
            'name' => 'API 項目',
            'unit' => '式',
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
        ]);

        $this->getJson(route('api.quotations.index', ['per_page' => 5]))
            ->assertOk()
            ->assertJsonPath('filters.search', '')
            ->assertJsonPath('statuses.draft', '草稿')
            ->assertJsonPath('quotations.per_page', 5)
            ->assertJsonPath('quotations.data.0.quotation_no', 'Q-API-0001')
            ->assertJsonPath('quotations.data.0.customer.name', 'API 報價客戶')
            ->assertJsonPath('quotations.data.0.items_count', 1);

        $this->getJson(route('api.quotations.show', $quotation))
            ->assertOk()
            ->assertJsonPath('statuses.draft', '草稿')
            ->assertJsonPath('quotation.quotation_no', 'Q-API-0001')
            ->assertJsonPath('quotation.customer.name', 'API 報價客戶')
            ->assertJsonPath('quotation.items.0.name', 'API 項目');
    }

    public function test_project_change_order_api_index_and_show_share_presenter_shape(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['read:project-change-orders']);

        $project = $this->project('TPH-API-CO-0001');
        $order = ProjectChangeOrder::create([
            'project_id' => $project->id,
            'title' => 'API 追加單',
            'amount' => 25000,
            'status' => 'draft',
        ]);

        $this->getJson(route('api.project-change-orders.index', ['per_page' => 5]))
            ->assertOk()
            ->assertJsonPath('filters.search', '')
            ->assertJsonPath('statuses.draft', '草稿')
            ->assertJsonPath('orders.per_page', 5)
            ->assertJsonPath('orders.data.0.title', 'API 追加單')
            ->assertJsonPath('orders.data.0.project.project_no', 'TPH-API-CO-0001')
            ->assertJsonPath('orders.data.0.project.customer.name', 'TPH-API-CO-0001 客戶')
            ->assertJsonPath('orders.data.0.can_submit_review', true);

        $this->getJson(route('api.project-change-orders.show', $order))
            ->assertOk()
            ->assertJsonPath('statuses.draft', '草稿')
            ->assertJsonPath('order.title', 'API 追加單')
            ->assertJsonPath('order.project.project_no', 'TPH-API-CO-0001')
            ->assertJsonPath('order.can_submit_review', true);
    }

    public function test_material_api_index_and_show_share_presenter_shape(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['read:materials']);

        $material = Material::create([
            'name' => 'API 鍍鋅角鐵',
            'spec' => 'L50x50',
            'unit' => '支',
            'cost_price' => 500,
            'sale_price' => 700,
            'safe_stock' => 3,
            'current_stock' => 2,
        ]);

        $this->getJson(route('api.materials.index', ['per_page' => 5, 'stock' => 'low']))
            ->assertOk()
            ->assertJsonPath('filters.stock', 'low')
            ->assertJsonPath('materials.per_page', 5)
            ->assertJsonPath('materials.data.0.name', 'API 鍍鋅角鐵')
            ->assertJsonPath('materials.data.0.current_stock', '2.000')
            ->assertJsonPath('units.0', '支');

        $this->getJson(route('api.materials.show', $material))
            ->assertOk()
            ->assertJsonPath('material.name', 'API 鍍鋅角鐵')
            ->assertJsonPath('material.spec', 'L50x50')
            ->assertJsonPath('material.inventory_transactions', []);
    }

    public function test_purchase_order_api_index_and_show_share_presenter_shape(): void
    {
        $user = $this->authorizedUser(roleCode: 'purchasing');
        Sanctum::actingAs($user, ['read:purchase-orders']);

        [$order, $material] = $this->purchaseOrder();

        $this->getJson(route('api.purchase-orders.index', ['per_page' => 5]))
            ->assertOk()
            ->assertJsonPath('filters.search', '')
            ->assertJsonPath('statuses.sent', '已送出')
            ->assertJsonPath('orders.per_page', 5)
            ->assertJsonPath('orders.data.0.purchase_order_no', $order->purchase_order_no)
            ->assertJsonPath('orders.data.0.supplier.name', 'API 讀取供應商');

        $this->getJson(route('api.purchase-orders.show', $order))
            ->assertOk()
            ->assertJsonPath('order.purchase_order_no', $order->purchase_order_no)
            ->assertJsonPath('order.items.0.material.name', $material->name)
            ->assertJsonPath('order.items.0.remaining_quantity', 6)
            ->assertJsonPath('order.can_receive', true);
    }

    public function test_api_show_keeps_project_linked_object_visibility(): void
    {
        $user = $this->userWithCapabilities([
            'projects.projects.view.assigned',
            'sales.quotations.view.tenant',
            'projects.change_orders.view.tenant',
        ]);
        Sanctum::actingAs($user, ['read:quotations', 'read:project-change-orders']);

        $project = $this->project('TPH-API-IDOR');
        $quotation = Quotation::create([
            'quotation_no' => 'Q-API-IDOR',
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'status' => 'draft',
            'subtotal' => 1000,
            'total' => 1000,
        ]);
        $order = ProjectChangeOrder::create([
            'project_id' => $project->id,
            'title' => '不可見 API 追加單',
            'amount' => 10000,
            'status' => 'draft',
        ]);

        $this->getJson(route('api.quotations.show', $quotation))->assertForbidden();
        $this->getJson(route('api.project-change-orders.show', $order))->assertForbidden();
    }

    public function test_external_token_must_have_matching_api_ability(): void
    {
        $user = $this->authorizedUser();
        $plainTextToken = $user
            ->createToken('Quotation read only', ['read:quotations'])
            ->plainTextToken;

        $this
            ->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson(route('api.quotations.index'))
            ->assertOk();

        $this
            ->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson(route('api.project-change-orders.index'))
            ->assertForbidden();

        $this
            ->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson(route('api.materials.index'))
            ->assertForbidden();
    }

    /**
     * @param  array<int, string>  $capabilityCodes
     */
    private function userWithCapabilities(array $capabilityCodes): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'API Read Role '.str()->random(8),
            'code' => 'api_read_role_'.str()->random(8),
        ]);

        $role->capabilities()->sync(
            Capability::query()
                ->whereIn('code', $capabilityCodes)
                ->pluck('id'),
        );
        $user->roles()->attach($role);

        return $user;
    }

    private function project(string $projectNo): Project
    {
        $customer = Customer::create(['name' => $projectNo.' 客戶']);

        return Project::create([
            'project_no' => $projectNo,
            'customer_id' => $customer->id,
            'name' => $projectNo.' 工程',
            'status' => 'inquiry',
        ]);
    }

    /**
     * @return array{0: PurchaseOrder, 1: Material}
     */
    private function purchaseOrder(): array
    {
        $supplier = Supplier::create(['name' => 'API 讀取供應商']);
        $material = Material::create([
            'name' => 'API 讀取材料',
            'unit' => '支',
            'current_stock' => 10,
        ]);
        $order = PurchaseOrder::create([
            'purchase_order_no' => 'PO-API-READ-'.str()->random(6),
            'supplier_id' => $supplier->id,
            'created_by' => User::factory()->create()->id,
            'status' => 'sent',
            'total' => 3600,
        ]);
        $order->items()->create([
            'material_id' => $material->id,
            'name' => $material->name,
            'unit' => '支',
            'quantity' => 8,
            'received_quantity' => 2,
            'unit_cost' => 450,
            'subtotal' => 3600,
        ]);

        return [$order, $material];
    }
}
