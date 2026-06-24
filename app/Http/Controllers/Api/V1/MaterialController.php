<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Models\Material;
use App\Presenters\Inventory\MaterialPresenter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MaterialController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    /**
     * @var array<int, string>
     */
    private const SORTS = [
        'created_at',
        'name',
        'current_stock',
        'safe_stock',
    ];

    public function __construct(private readonly MaterialPresenter $materialPresenter) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'integer'],
            'stock' => ['nullable', 'string', 'in:low'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'sort' => ['nullable', 'string'],
        ]);

        $sort = $this->sort($validated['sort'] ?? '-created_at');
        $search = trim((string) ($validated['search'] ?? ''));
        $categoryId = $validated['category'] ?? null;
        $stock = $validated['stock'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        $materials = Material::query()
            ->with('category:id,name,code')
            ->withCount(['quotationItems', 'inventoryTransactions'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('spec', 'ilike', "%{$search}%")
                    ->orWhere('unit', 'ilike', "%{$search}%");
            }))
            ->when($categoryId, fn ($query) => $query->where('material_category_id', $categoryId))
            ->when($stock === 'low', fn ($query) => $query->whereColumn('current_stock', '<=', 'safe_stock'))
            ->orderBy($sort['field'], $sort['direction'])
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Material $material) => $this->materialPresenter->indexItem($material));

        return ApiResponse::success(
            data: $materials->items(),
            meta: [
                'pagination' => ApiResponse::paginationMeta($materials),
                'filters' => [
                    'search' => $search,
                    'category' => $categoryId,
                    'stock' => $stock,
                    'sort' => $sort['requested'],
                ],
                'categories' => $this->materialPresenter->categories(),
                'units' => $this->materialPresenter->units(),
            ],
            links: ApiResponse::paginationLinks($materials),
        );
    }

    public function show(Material $material): JsonResponse
    {
        $material->load([
            'category:id,name,code',
            'quotationItems' => fn ($query) => $query->with('quotation:id,quotation_no,status,total')->latest()->limit(8),
            'inventoryTransactions' => fn ($query) => $query->with(['project:id,project_no,name', 'creator:id,name'])->latest()->limit(12),
        ]);

        return ApiResponse::success(
            data: $this->materialPresenter->show($material),
        );
    }

    public function store(StoreMaterialRequest $request): JsonResponse
    {
        $material = Material::create($this->materialPresenter->materialData($request->validated()));
        $material->load('category:id,name,code');

        return ApiResponse::success(
            data: $this->materialPresenter->show($material),
            message: '材料品項已建立。',
            status: 201,
        );
    }

    public function update(UpdateMaterialRequest $request, Material $material): JsonResponse
    {
        $data = $this->materialPresenter->materialData($request->validated());
        $data['current_stock'] = $material->current_stock;
        $material->update($data);

        $material->refresh()->load('category:id,name,code');

        return ApiResponse::success(
            data: $this->materialPresenter->show($material),
            message: '材料品項已更新。',
        );
    }

    public function destroy(Material $material): JsonResponse
    {
        $id = $material->id;
        $material->delete();

        return ApiResponse::success(
            data: ['deleted_material_id' => $id],
            message: '材料品項已刪除。',
        );
    }

    /**
     * @return array{field: string, direction: string, requested: string}
     */
    private function sort(string $sort): array
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        if (! in_array($field, self::SORTS, true)) {
            throw ValidationException::withMessages([
                'sort' => ['The selected sort is invalid.'],
            ]);
        }

        return [
            'field' => $field,
            'direction' => $direction,
            'requested' => ($direction === 'desc' ? '-' : '').$field,
        ];
    }
}
