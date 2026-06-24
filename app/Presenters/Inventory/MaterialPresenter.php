<?php

namespace App\Presenters\Inventory;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Presenters\Concerns\PresentsModelSummaries;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MaterialPresenter
{
    use PresentsModelSummaries;

    /**
     * @return array<string, mixed>
     */
    public function indexItem(Material $material): array
    {
        return [
            ...$this->base($material),
            'quotation_items_count' => $material->quotation_items_count,
            'inventory_transactions_count' => $material->inventory_transactions_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Material $material): array
    {
        return [
            ...$this->base($material),
            'quotation_items' => $material->quotationItems
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'quotation' => $this->quotationSummary($item->quotation, ['id', 'quotation_no', 'status', 'total']),
                    'name' => $item->name,
                    'spec' => $item->spec,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ])
                ->values(),
            'inventory_transactions' => $material->inventoryTransactions
                ->map(fn ($transaction) => [
                    'id' => $transaction->id,
                    'project' => $this->projectSummary($transaction->project),
                    'creator' => $this->userSummary($transaction->creator),
                    'type' => $transaction->type,
                    'quantity' => $transaction->quantity,
                    'unit' => $transaction->unit,
                    'unit_cost' => $transaction->unit_cost,
                    'total_cost' => $transaction->total_cost,
                    'reference_no' => $transaction->reference_no,
                    'occurred_at' => $transaction->occurred_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                ])
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function form(Material $material): array
    {
        return [
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
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function categories()
    {
        return MaterialCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (MaterialCategory $category) => $this->modelOnly($category, ['id', 'name', 'code']));
    }

    /**
     * @return array<int, string>
     */
    public function units(): array
    {
        return ['支', '片', '包', '箱', 'kg', '噸', '坪', '才', '米', '公尺', '組', '式'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function materialData(array $data): array
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

    /**
     * @return array<string, mixed>
     */
    private function base(Material $material): array
    {
        return [
            'id' => $material->id,
            'name' => $material->name,
            'spec' => $material->spec,
            'unit' => $material->unit,
            'category' => $this->modelOnly($material->category, ['id', 'name', 'code']),
            'length' => $material->length,
            'width' => $material->width,
            'thickness' => $material->thickness,
            'weight' => $material->weight,
            'cost_price' => $material->cost_price,
            'sale_price' => $material->sale_price,
            'safe_stock' => $material->safe_stock,
            'current_stock' => $material->current_stock,
        ];
    }
}
