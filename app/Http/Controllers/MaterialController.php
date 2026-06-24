<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Models\Material;
use App\Presenters\Inventory\MaterialPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends Controller
{
    public function __construct(private readonly MaterialPresenter $materialPresenter) {}

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
            ->through(fn (Material $material) => $this->materialPresenter->indexItem($material));

        return Inertia::render('Materials/Index', [
            'materials' => $materials,
            'categories' => $this->materialPresenter->categories(),
            'filters' => [
                'search' => $search,
                'category' => $categoryId,
                'stock' => $stock,
            ],
            'units' => $this->materialPresenter->units(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Materials/Create', [
            'categories' => $this->materialPresenter->categories(),
            'units' => $this->materialPresenter->units(),
        ]);
    }

    public function store(StoreMaterialRequest $request): RedirectResponse
    {
        $material = Material::create($this->materialPresenter->materialData($request->validated()));

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
            'material' => $this->materialPresenter->show($material),
        ]);
    }

    public function edit(Material $material): Response
    {
        return Inertia::render('Materials/Edit', [
            'material' => $this->materialPresenter->form($material),
            'categories' => $this->materialPresenter->categories(),
            'units' => $this->materialPresenter->units(),
        ]);
    }

    public function update(UpdateMaterialRequest $request, Material $material): RedirectResponse
    {
        $data = $this->materialPresenter->materialData($request->validated());
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
}
