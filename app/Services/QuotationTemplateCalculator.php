<?php

namespace App\Services;

use App\Models\QuotationTemplate;
use App\Models\QuotationTemplateItem;

class QuotationTemplateCalculator
{
    /**
     * @param  array<string, mixed>  $inputs
     * @return array<int, array<string, mixed>>
     */
    public function items(QuotationTemplate $template, array $inputs): array
    {
        $template->loadMissing('items.material:id,name,spec,unit,cost_price,sale_price');

        return $template->items
            ->map(fn (QuotationTemplateItem $item) => $this->item($item, $inputs))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function item(QuotationTemplateItem $item, array $inputs): array
    {
        $quantity = $this->quantity($item, $inputs);

        return [
            'material_id' => $item->material_id,
            'name' => $item->name,
            'spec' => $item->spec,
            'unit' => $item->unit,
            'quantity' => $quantity,
            'unit_price' => $item->unit_price,
            'cost_price' => $item->cost_price,
            'waste_rate' => $item->waste_rate,
            'note' => $item->note,
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function quantity(QuotationTemplateItem $item, array $inputs): float
    {
        $params = $item->formula_params ?? [];
        $base = match ($item->formula_type) {
            'area_based' => $this->value($inputs, $params['length_param'] ?? 'length')
                * $this->value($inputs, $params['width_param'] ?? 'width'),
            'length_based' => $this->divide(
                $this->value($inputs, $params['length_param'] ?? 'length'),
                $this->value($inputs, $params['spacing_param'] ?? 'spacing'),
            ),
            'panel_count' => $this->divide(
                $this->value($inputs, $params['length_param'] ?? 'length')
                    * $this->value($inputs, $params['width_param'] ?? 'width'),
                $this->value($inputs, $params['effective_width_param'] ?? 'panel_effective_width')
                    * $this->value($inputs, $params['panel_length_param'] ?? 'panel_length'),
            ),
            'perimeter_based' => $this->divide(
                2 * (
                    $this->value($inputs, $params['length_param'] ?? 'length')
                    + $this->value($inputs, $params['width_param'] ?? 'width')
                ),
                $this->value($inputs, $params['piece_length_param'] ?? 'piece_length'),
            ),
            default => $this->number($params['quantity'] ?? 1),
        };

        $withWaste = $base * (1 + ((float) $item->waste_rate / 100));

        return round(max(0, $withWaste), 3);
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function value(array $inputs, string $key): float
    {
        return $this->number($inputs[$key] ?? 0);
    }

    private function divide(float $numerator, float $denominator): float
    {
        if ($denominator <= 0) {
            return 0;
        }

        return $numerator / $denominator;
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0;
    }
}
