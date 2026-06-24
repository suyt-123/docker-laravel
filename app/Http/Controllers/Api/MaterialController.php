<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Models\Material;
use App\Presenters\Inventory\MaterialPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function __construct(private readonly MaterialPresenter $materialPresenter) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = trim((string) $request->query('category', ''));
        $stock = trim((string) $request->query('stock', ''));
        $perPage = min(max((int) $request->query('per_page', 12), 1), 100);

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
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Material $material) => $this->materialPresenter->indexItem($material));

        return response()->json([
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

    public function show(Material $material): JsonResponse
    {
        $material->load([
            'category:id,name,code',
            'quotationItems' => fn ($query) => $query->with('quotation:id,quotation_no,status,total')->latest()->limit(8),
            'inventoryTransactions' => fn ($query) => $query->with(['project:id,project_no,name', 'creator:id,name'])->latest()->limit(12),
        ]);

        return response()->json([
            'material' => $this->materialPresenter->show($material),
        ]);
    }

    public function store(StoreMaterialRequest $request): JsonResponse
    {
        $material = Material::create($this->materialPresenter->materialData($request->validated()));
        $material->load('category:id,name,code');

        return response()->json([
            'message' => '材料品項已建立。',
            'material' => $this->materialPresenter->show($material),
        ], 201);
    }

    public function update(UpdateMaterialRequest $request, Material $material): JsonResponse
    {
        $data = $this->materialPresenter->materialData($request->validated());
        $data['current_stock'] = $material->current_stock;
        $material->update($data);

        $material->refresh()->load('category:id,name,code');

        return response()->json([
            'message' => '材料品項已更新。',
            'material' => $this->materialPresenter->show($material),
        ]);
    }

    public function destroy(Material $material): JsonResponse
    {
        $id = $material->id;
        $material->delete();

        return response()->json([
            'message' => '材料品項已刪除。',
            'deleted_material_id' => $id,
        ]);
    }
}
