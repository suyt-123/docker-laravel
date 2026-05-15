<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Models\Material;
use App\Models\MaterialCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = trim((string) $request->query('category', ''));
        $stock = trim((string) $request->query('stock', ''));

        $materials = Material::query()
            ->with('category:id,name,code')
            ->withCount(['quotationItems', 'inventoryTransactions'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('spec', 'ilike', "%{$search}%")
                    ->orWhere('unit', 'ilike', "%{$search}%");
            }))
            ->when($categoryId !== '', fn ($query) => $query->where('material_category_id', $categoryId))
            ->when($stock === 'low', fn ($query) => $query->whereColumn('current_stock', '<=', 'safe_stock'))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Material $material) => [
                'id' => $material->id,
                'name' => $material->name,
                'spec' => $material->spec,
                'unit' => $material->unit,
                'category' => $material->category,
                'length' => $material->length,
                'width' => $material->width,
                'thickness' => $material->thickness,
                'weight' => $material->weight,
                'cost_price' => $material->cost_price,
                'sale_price' => $material->sale_price,
                'safe_stock' => $material->safe_stock,
                'current_stock' => $material->current_stock,
                'quotation_items_count' => $material->quotation_items_count,
                'inventory_transactions_count' => $material->inventory_transactions_count,
            ]);

        return Inertia::render('Materials/Index', [
            'materials' => $materials,
            'categories' => $this->categories(),
            'filters' => [
                'search' => $search,
                'category' => $categoryId,
                'stock' => $stock,
            ],
            'units' => $this->units(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Materials/Create', [
            'categories' => $this->categories(),
            'units' => $this->units(),
        ]);
    }

    public function store(StoreMaterialRequest $request): RedirectResponse
    {
        $material = Material::create($this->materialData($request->validated()));

        return redirect()
            ->route('materials.show', $material)
            ->with('success', '材料品項已建立。');
    }

    public function show(Material $material): Response
    {
        $material->load([
            'category:id,name,code',
            'quotationItems' => fn ($query) => $query->with('quotation:id,quotation_no,status,total')->latest()->limit(8),
            'inventoryTransactions' => fn ($query) => $query->with('project:id,project_no,name')->latest()->limit(12),
        ]);

        return Inertia::render('Materials/Show', [
            'material' => [
                'id' => $material->id,
                'name' => $material->name,
                'spec' => $material->spec,
                'unit' => $material->unit,
                'category' => $material->category,
                'length' => $material->length,
                'width' => $material->width,
                'thickness' => $material->thickness,
                'weight' => $material->weight,
                'cost_price' => $material->cost_price,
                'sale_price' => $material->sale_price,
                'safe_stock' => $material->safe_stock,
                'current_stock' => $material->current_stock,
                'quotation_items' => $material->quotationItems,
                'inventory_transactions' => $material->inventoryTransactions,
            ],
        ]);
    }

    public function edit(Material $material): Response
    {
        return Inertia::render('Materials/Edit', [
            'material' => [
                'id' => $material->id,
                'material_category_id' => $material->material_category_id,
                'category_name' => '',
                'name' => $material->name,
                'spec' => $material->spec,
                'unit' => $material->unit,
                'length' => $material->length,
                'width' => $material->width,
                'thickness' => $material->thickness,
                'weight' => $material->weight,
                'cost_price' => $material->cost_price,
                'sale_price' => $material->sale_price,
                'safe_stock' => $material->safe_stock,
                'current_stock' => $material->current_stock,
            ],
            'categories' => $this->categories(),
            'units' => $this->units(),
        ]);
    }

    public function update(UpdateMaterialRequest $request, Material $material): RedirectResponse
    {
        $data = $this->materialData($request->validated());
        $data['current_stock'] = $material->current_stock;

        $material->update($data);

        return redirect()
            ->route('materials.show', $material)
            ->with('success', '材料品項已更新。');
    }

    public function destroy(Material $material): RedirectResponse
    {
        $material->delete();

        return redirect()
            ->route('materials.index')
            ->with('success', '材料品項已刪除。');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, MaterialCategory>
     */
    private function categories()
    {
        return MaterialCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /**
     * @return array<int, string>
     */
    private function units(): array
    {
        return ['支', '片', '包', '箱', 'kg', '噸', '坪', '才', '米', '公尺', '組', '式'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function materialData(array $data): array
    {
        if (filled($data['category_name'] ?? null)) {
            $categoryName = trim((string) $data['category_name']);
            $category = MaterialCategory::firstOrCreate(
                ['code' => Str::slug($categoryName) ?: Str::lower(Str::random(8))],
                ['name' => $categoryName],
            );
            $data['material_category_id'] = $category->id;
        }

        unset($data['category_name']);

        return [
            ...$data,
            'cost_price' => (int) ($data['cost_price'] ?? 0),
            'sale_price' => (int) ($data['sale_price'] ?? 0),
            'safe_stock' => $data['safe_stock'] ?? 0,
            'current_stock' => $data['current_stock'] ?? 0,
        ];
    }
}
