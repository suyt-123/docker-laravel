<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_material_pages(): void
    {
        $this->get(route('materials.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_material_index(): void
    {
        $user = $this->authorizedUser();
        $category = MaterialCategory::create([
            'name' => 'C 型鋼',
            'code' => 'c-channel',
        ]);

        Material::create([
            'material_category_id' => $category->id,
            'name' => 'C 型鋼',
            'spec' => '100x50x20x2.3mm',
            'unit' => '支',
        ]);

        $this
            ->actingAs($user)
            ->get(route('materials.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Materials/Index')
                ->has('materials.data', 1)
            );
    }

    public function test_authenticated_user_can_create_material_with_new_category(): void
    {
        $user = $this->authorizedUser();

        $this
            ->actingAs($user)
            ->post(route('materials.store'), [
                'category_name' => 'H 鋼',
                'name' => 'H 鋼',
                'spec' => '200x100x5.5x8mm',
                'unit' => '支',
                'length' => 6,
                'weight' => 21.7,
                'cost_price' => 3200,
                'sale_price' => 3900,
                'safe_stock' => 5,
                'current_stock' => 12,
            ])
            ->assertRedirect();

        $category = MaterialCategory::where('name', 'H 鋼')->firstOrFail();

        $this->assertDatabaseHas('materials', [
            'material_category_id' => $category->id,
            'name' => 'H 鋼',
            'spec' => '200x100x5.5x8mm',
            'unit' => '支',
            'cost_price' => 3200,
            'sale_price' => 3900,
        ]);
    }

    public function test_material_requires_name_and_unit(): void
    {
        $user = $this->authorizedUser();

        $this
            ->actingAs($user)
            ->from(route('materials.create'))
            ->post(route('materials.store'), [
                'name' => '',
                'unit' => '',
            ])
            ->assertRedirect(route('materials.create'))
            ->assertSessionHasErrors(['name', 'unit']);
    }

    public function test_authenticated_user_can_update_material(): void
    {
        $user = $this->authorizedUser();
        $category = MaterialCategory::create([
            'name' => '浪板',
            'code' => 'roof-sheet',
        ]);
        $material = Material::create([
            'material_category_id' => $category->id,
            'name' => '烤漆浪板',
            'unit' => '片',
        ]);

        $this
            ->actingAs($user)
            ->patch(route('materials.update', $material), [
                'material_category_id' => $category->id,
                'category_name' => '',
                'name' => '烤漆浪板',
                'spec' => '0.5mm',
                'unit' => '片',
                'cost_price' => 450,
                'sale_price' => 620,
                'safe_stock' => 30,
                'current_stock' => 80,
            ])
            ->assertRedirect(route('materials.show', $material));

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'spec' => '0.5mm',
            'cost_price' => 450,
            'sale_price' => 620,
        ]);
    }

    public function test_authenticated_user_can_delete_unused_material(): void
    {
        $user = $this->authorizedUser();
        $material = Material::create([
            'name' => '可刪材料',
            'unit' => '支',
        ]);

        $this
            ->actingAs($user)
            ->delete(route('materials.destroy', $material))
            ->assertRedirect(route('materials.index'));

        $this->assertDatabaseMissing('materials', [
            'id' => $material->id,
        ]);
    }
}
