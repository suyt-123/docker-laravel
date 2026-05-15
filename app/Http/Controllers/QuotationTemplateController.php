<?php

namespace App\Http\Controllers;

use App\Auth\CapabilityAuthorizer;
use App\Http\Requests\StoreQuotationTemplateRequest;
use App\Http\Requests\UpdateQuotationTemplateRequest;
use App\Models\Material;
use App\Models\QuotationTemplate;
use App\Services\QuotationTemplateCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuotationTemplateController extends Controller
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly QuotationTemplateCalculator $calculator,
    ) {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $templates = QuotationTemplate::query()
            ->withCount('items')
            ->when(! $this->canManage($request), fn ($query) => $query->where('status', 'active'))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('type', 'ilike', "%{$search}%");
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (QuotationTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'type' => $template->type,
                'status' => $template->status,
                'profit_rate' => $template->profit_rate,
                'tax' => $template->tax,
                'discount' => $template->discount,
                'items_count' => $template->items_count,
            ]);

        return Inertia::render('QuotationTemplates/Index', [
            'templates' => $templates,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('QuotationTemplates/Create', [
            'options' => $this->formOptions(),
            'statuses' => $this->statuses(),
            'formulaTypes' => $this->formulaTypes(),
        ]);
    }

    public function store(StoreQuotationTemplateRequest $request): RedirectResponse
    {
        $template = QuotationTemplate::create($this->templateData($request->validated()));
        $this->syncItems($template, $request->validated('items'));

        return redirect()
            ->route('quotation-templates.show', $template)
            ->with('success', '報價模板已建立。');
    }

    public function show(Request $request, QuotationTemplate $quotationTemplate): Response
    {
        abort_if($quotationTemplate->status !== 'active' && ! $this->canManage($request), 404);

        $quotationTemplate->load('items.material:id,name,spec,unit,cost_price,sale_price');

        return Inertia::render('QuotationTemplates/Show', [
            'template' => $this->templatePayload($quotationTemplate),
            'statuses' => $this->statuses(),
            'formulaTypes' => $this->formulaTypes(),
        ]);
    }

    public function edit(QuotationTemplate $quotationTemplate): Response
    {
        $quotationTemplate->load('items');

        return Inertia::render('QuotationTemplates/Edit', [
            'template' => $this->templatePayload($quotationTemplate),
            'options' => $this->formOptions(),
            'statuses' => $this->statuses(),
            'formulaTypes' => $this->formulaTypes(),
        ]);
    }

    public function update(UpdateQuotationTemplateRequest $request, QuotationTemplate $quotationTemplate): RedirectResponse
    {
        $quotationTemplate->update($this->templateData($request->validated()));
        $this->syncItems($quotationTemplate, $request->validated('items'));

        return redirect()
            ->route('quotation-templates.show', $quotationTemplate)
            ->with('success', '報價模板已更新。');
    }

    public function destroy(QuotationTemplate $quotationTemplate): RedirectResponse
    {
        $quotationTemplate->delete();

        return redirect()
            ->route('quotation-templates.index')
            ->with('success', '報價模板已刪除。');
    }

    public function calculate(Request $request, QuotationTemplate $quotationTemplate): JsonResponse
    {
        abort_unless($quotationTemplate->status === 'active', 422, '只有啟用中的報價模板可以套用。');

        $data = $request->validate([
            'inputs' => ['nullable', 'array'],
        ]);

        $inputs = $this->normalizeInputs($quotationTemplate, $data['inputs'] ?? []);

        return response()->json([
            'template' => $this->templatePayload($quotationTemplate->load('items')),
            'inputs' => $inputs,
            'items' => $this->calculator->items($quotationTemplate, $inputs),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return [
            'active' => '啟用',
            'inactive' => '停用',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function formulaTypes(): array
    {
        return [
            'fixed_quantity' => '固定數量',
            'area_based' => '面積估算',
            'length_based' => '長度 / 間距',
            'panel_count' => '板材片數',
            'perimeter_based' => '周長估算',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'materials' => Material::query()
                ->with('category:id,name')
                ->orderBy('name')
                ->get(['id', 'material_category_id', 'name', 'spec', 'unit', 'cost_price', 'sale_price']),
        ];
    }

    private function canManage(Request $request): bool
    {
        return $this->authorizer->allows($request->user(), 'sales.quotation_templates.create.tenant')
            || $this->authorizer->allows($request->user(), 'sales.quotation_templates.update.tenant')
            || $this->authorizer->allows($request->user(), 'sales.quotation_templates.delete.tenant');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function templateData(array $data): array
    {
        unset($data['items']);

        return [
            ...$data,
            'profit_rate' => $data['profit_rate'] ?? 0,
            'tax' => (int) ($data['tax'] ?? 0),
            'discount' => (int) ($data['discount'] ?? 0),
            'parameter_definitions' => collect($data['parameter_definitions'] ?? [])
                ->map(fn (array $definition) => [
                    'key' => $definition['key'],
                    'label' => $definition['label'],
                    'unit' => $definition['unit'] ?? null,
                    'default' => $definition['default'] ?? null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(QuotationTemplate $template, array $items): void
    {
        $template->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $template->items()->create([
                'material_id' => $item['material_id'] ?? null,
                'name' => $item['name'],
                'spec' => $item['spec'] ?? null,
                'unit' => $item['unit'],
                'unit_price' => (int) $item['unit_price'],
                'cost_price' => (int) ($item['cost_price'] ?? 0),
                'waste_rate' => $item['waste_rate'] ?? 0,
                'formula_type' => $item['formula_type'],
                'formula_params' => $item['formula_params'] ?? [],
                'note' => $item['note'] ?? null,
                'sort_order' => $item['sort_order'] ?? $index,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(QuotationTemplate $template): array
    {
        $template->loadMissing('items.material:id,name,spec,unit,cost_price,sale_price');

        return [
            'id' => $template->id,
            'name' => $template->name,
            'type' => $template->type,
            'status' => $template->status,
            'profit_rate' => $template->profit_rate,
            'tax' => $template->tax,
            'discount' => $template->discount,
            'parameter_definitions' => $template->parameter_definitions ?? [],
            'note' => $template->note,
            'items' => $template->items->map(fn ($item) => [
                'id' => $item->id,
                'material_id' => $item->material_id,
                'material' => $item->material,
                'name' => $item->name,
                'spec' => $item->spec,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'cost_price' => $item->cost_price,
                'waste_rate' => $item->waste_rate,
                'formula_type' => $item->formula_type,
                'formula_params' => $item->formula_params ?? [],
                'note' => $item->note,
                'sort_order' => $item->sort_order,
            ])->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function normalizeInputs(QuotationTemplate $template, array $inputs): array
    {
        return collect($template->parameter_definitions ?? [])
            ->mapWithKeys(function (array $definition) use ($inputs) {
                $key = $definition['key'];
                $value = $inputs[$key] ?? $definition['default'] ?? 0;

                return [$key => is_numeric($value) ? (float) $value : 0];
            })
            ->all();
    }
}
