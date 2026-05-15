<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEquipmentCategoryRequest;
use App\Http\Requests\UpdateEquipmentCategoryRequest;
use App\Models\EquipmentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EquipmentCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $categories = EquipmentCategory::query()
            ->withCount('equipment')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            }))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('EquipmentCategories/Index', [
            'categories' => $categories,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('EquipmentCategories/Create');
    }

    public function store(StoreEquipmentCategoryRequest $request): RedirectResponse
    {
        $category = EquipmentCategory::create($this->categoryData($request->validated()));

        return redirect()
            ->route('equipment-categories.show', $category)
            ->with('success', '工具與機具分類已建立。');
    }

    public function show(EquipmentCategory $equipmentCategory): Response
    {
        $equipmentCategory->load([
            'equipment' => fn ($query) => $query->latest()->limit(12),
        ]);

        return Inertia::render('EquipmentCategories/Show', [
            'category' => [
                ...$equipmentCategory->toArray(),
                'equipment' => $equipmentCategory->equipment,
            ],
        ]);
    }

    public function edit(EquipmentCategory $equipmentCategory): Response
    {
        return Inertia::render('EquipmentCategories/Edit', [
            'category' => $equipmentCategory,
        ]);
    }

    public function update(UpdateEquipmentCategoryRequest $request, EquipmentCategory $equipmentCategory): RedirectResponse
    {
        $equipmentCategory->update($this->categoryData($request->validated()));

        return redirect()
            ->route('equipment-categories.show', $equipmentCategory)
            ->with('success', '工具與機具分類已更新。');
    }

    public function destroy(EquipmentCategory $equipmentCategory): RedirectResponse
    {
        abort_if($equipmentCategory->equipment()->exists(), 422, '已有設備使用的分類不可刪除。');

        $equipmentCategory->delete();

        return redirect()
            ->route('equipment-categories.index')
            ->with('success', '工具與機具分類已刪除。');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function categoryData(array $data): array
    {
        return [
            ...$data,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }
}
