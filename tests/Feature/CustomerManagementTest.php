<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_customer_pages(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_customer_index(): void
    {
        $user = $this->authorizedUser();
        Customer::create([
            'name' => '台北鐵皮工程',
            'phone' => '02-2222-3333',
        ]);

        $this
            ->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Customers/Index')
                ->has('customers.data', 1)
            );
    }

    public function test_authenticated_user_can_create_customer_with_primary_contact(): void
    {
        $user = $this->authorizedUser();

        $this
            ->actingAs($user)
            ->post(route('customers.store'), [
                'name' => '新北鋼構廠房',
                'phone' => '02-1234-5678',
                'line_id' => 'steel-demo',
                'tax_id' => '12345678',
                'source' => '介紹',
                'address' => '新北市五股區測試路 1 號',
                'primary_contact' => [
                    'name' => '陳先生',
                    'title' => '廠長',
                    'phone' => '0912-000-000',
                    'email' => 'chen@example.com',
                    'line_id' => 'chen-line',
                ],
            ])
            ->assertRedirect();

        $customer = Customer::where('name', '新北鋼構廠房')->firstOrFail();

        $this->assertSame('steel-demo', $customer->line_id);
        $this->assertDatabaseHas('customer_contacts', [
            'customer_id' => $customer->id,
            'name' => '陳先生',
            'is_primary' => true,
        ]);
    }

    public function test_customer_requires_a_name(): void
    {
        $user = $this->authorizedUser();

        $this
            ->actingAs($user)
            ->from(route('customers.create'))
            ->post(route('customers.store'), [
                'name' => '',
            ])
            ->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors('name');
    }

    public function test_authenticated_user_can_update_customer(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '舊客戶']);

        $this
            ->actingAs($user)
            ->patch(route('customers.update', $customer), [
                'name' => '更新後客戶',
                'phone' => '02-9999-8888',
                'primary_contact' => [
                    'name' => '林小姐',
                    'email' => 'lin@example.com',
                ],
            ])
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => '更新後客戶',
            'phone' => '02-9999-8888',
        ]);
        $this->assertDatabaseHas('customer_contacts', [
            'customer_id' => $customer->id,
            'name' => '林小姐',
            'email' => 'lin@example.com',
        ]);
    }

    public function test_authenticated_user_can_delete_customer_without_projects(): void
    {
        $user = $this->authorizedUser();
        $customer = Customer::create(['name' => '可刪除客戶']);

        $this
            ->actingAs($user)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);
    }
}
