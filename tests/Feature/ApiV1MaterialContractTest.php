<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiV1MaterialContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_requires_authentication_with_error_envelope(): void
    {
        $this->getJson(route('api.v1.materials.index'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta', []);
    }

    public function test_v1_requires_rbac_capability_with_error_envelope(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['read:materials']);

        $this->getJson(route('api.v1.materials.index'))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta', []);
    }

    public function test_v1_requires_matching_token_ability_with_error_envelope(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['read:quotations']);

        $this->getJson(route('api.v1.materials.index'))
            ->assertForbidden()
            ->assertJsonPath('code', 'token_ability_missing')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta.ability', 'read:materials');
    }

    public function test_v1_material_index_uses_standard_success_envelope(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['read:materials']);

        Material::create([
            'name' => 'API V1 鍍鋅角鐵',
            'spec' => 'L50x50',
            'unit' => '支',
            'safe_stock' => 3,
            'current_stock' => 2,
        ]);

        $this->getJson(route('api.v1.materials.index', [
            'per_page' => 5,
            'stock' => 'low',
            'sort' => 'name',
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'API V1 鍍鋅角鐵')
            ->assertJsonPath('data.0.current_stock', '2.000')
            ->assertJsonPath('meta.pagination.per_page', 5)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('meta.filters.stock', 'low')
            ->assertJsonPath('meta.filters.sort', 'name')
            ->assertJsonPath('meta.units.0', '支')
            ->assertJsonPath('links.prev', null)
            ->assertJsonPath('message', null);
    }

    public function test_v1_material_show_uses_standard_success_envelope(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['read:materials']);

        $material = Material::create([
            'name' => 'API V1 展示材料',
            'spec' => 'T2.0',
            'unit' => '片',
        ]);

        $this->getJson(route('api.v1.materials.show', $material))
            ->assertOk()
            ->assertJsonPath('data.name', 'API V1 展示材料')
            ->assertJsonPath('data.spec', 'T2.0')
            ->assertJsonPath('data.inventory_transactions', [])
            ->assertJsonPath('meta', [])
            ->assertJsonPath('links', [])
            ->assertJsonPath('message', null);
    }

    public function test_v1_material_index_returns_validation_error_envelope_for_invalid_sort(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['read:materials']);

        $this->getJson(route('api.v1.materials.index', ['sort' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.sort.0', 'The selected sort is invalid.')
            ->assertJsonPath('meta', []);
    }

    public function test_v1_material_store_uses_success_and_validation_envelopes(): void
    {
        $user = $this->authorizedUser();
        Sanctum::actingAs($user, ['write:materials']);

        $this->postJson(route('api.v1.materials.store'), [
            'unit' => '支',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.name.0', 'The name field is required.');

        $this->postJson(route('api.v1.materials.store'), [
            'name' => 'API V1 新材料',
            'unit' => '支',
            'current_stock' => 9,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'API V1 新材料')
            ->assertJsonPath('data.current_stock', '9.000')
            ->assertJsonPath('meta', [])
            ->assertJsonPath('links', [])
            ->assertJsonPath('message', '材料品項已建立。');
    }
}
