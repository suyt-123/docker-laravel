<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\QuotationTemplate;
use Illuminate\Database\Seeder;

class QuotationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $templateData) {
            $items = $templateData['items'];
            unset($templateData['items']);

            $template = QuotationTemplate::updateOrCreate(
                ['name' => $templateData['name']],
                $templateData,
            );

            $template->items()->delete();

            foreach ($items as $index => $item) {
                $material = Material::query()
                    ->where('name', 'ilike', '%'.$item['name'].'%')
                    ->first();

                $template->items()->create([
                    ...$item,
                    'material_id' => $material?->id,
                    'spec' => $material?->spec ?? $item['spec'] ?? null,
                    'unit' => $material?->unit ?? $item['unit'],
                    'cost_price' => $material?->cost_price ?? $item['cost_price'],
                    'unit_price' => $material?->sale_price ?? $item['unit_price'],
                    'sort_order' => $index,
                ]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        $standardParameters = [
            ['key' => 'length', 'label' => '長度', 'unit' => 'm', 'default' => 10],
            ['key' => 'width', 'label' => '寬度', 'unit' => 'm', 'default' => 6],
            ['key' => 'spacing', 'label' => '柱距 / 間距', 'unit' => 'm', 'default' => 1.2],
            ['key' => 'panel_effective_width', 'label' => '板材有效寬度', 'unit' => 'm', 'default' => 0.76],
            ['key' => 'panel_length', 'label' => '板材長度', 'unit' => 'm', 'default' => 6],
            ['key' => 'piece_length', 'label' => '單支原材長度', 'unit' => 'm', 'default' => 6],
        ];

        return [
            [
                'name' => '一般鐵皮屋',
                'type' => '鐵皮屋',
                'status' => 'active',
                'profit_rate' => 25,
                'tax' => 0,
                'discount' => 0,
                'parameter_definitions' => $standardParameters,
                'note' => '一般鐵皮屋模板，可依現場尺寸調整。',
                'items' => [
                    $this->item('C 型鋼', '支', 1100, 850, 'length_based', 5),
                    $this->item('烤漆浪板', '片', 900, 650, 'panel_count', 8),
                    $this->item('收邊', '支', 450, 320, 'perimeter_based', 5),
                    $this->fixed('螺絲與五金', '式', 5000, 3500),
                    $this->fixed('施工工資', '式', 30000, 22000),
                ],
            ],
            [
                'name' => '採光罩',
                'type' => '採光罩',
                'status' => 'active',
                'profit_rate' => 25,
                'tax' => 0,
                'discount' => 0,
                'parameter_definitions' => $standardParameters,
                'note' => '採光罩模板，適合入口、車道與露臺。',
                'items' => [
                    $this->item('採光板', '片', 1200, 850, 'panel_count', 8),
                    $this->item('方管', '支', 900, 650, 'perimeter_based', 5),
                    $this->fixed('矽利康與五金', '式', 3500, 2200),
                    $this->fixed('施工工資', '式', 18000, 12000),
                ],
            ],
            [
                'name' => '雨遮',
                'type' => '雨遮',
                'status' => 'active',
                'profit_rate' => 25,
                'tax' => 0,
                'discount' => 0,
                'parameter_definitions' => $standardParameters,
                'note' => '雨遮模板，適合店面、住家入口。',
                'items' => [
                    $this->item('烤漆浪板', '片', 900, 650, 'panel_count', 8),
                    $this->item('角鐵', '支', 500, 350, 'perimeter_based', 5),
                    $this->fixed('五金配件', '式', 2500, 1500),
                    $this->fixed('施工工資', '式', 12000, 8000),
                ],
            ],
            [
                'name' => 'H 鋼結構',
                'type' => '鋼構',
                'status' => 'active',
                'profit_rate' => 28,
                'tax' => 0,
                'discount' => 0,
                'parameter_definitions' => $standardParameters,
                'note' => 'H 鋼結構模板，第一版以長度與周長粗估。',
                'items' => [
                    $this->item('H 鋼', '支', 5800, 4300, 'length_based', 5),
                    $this->item('C 型鋼', '支', 1100, 850, 'length_based', 8),
                    $this->fixed('焊條與油漆', '式', 8000, 5500),
                    $this->fixed('吊車與機具', '式', 18000, 14000),
                    $this->fixed('施工工資', '式', 45000, 32000),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $name, string $unit, int $unitPrice, int $costPrice, string $formulaType, float $wasteRate): array
    {
        return [
            'name' => $name,
            'unit' => $unit,
            'unit_price' => $unitPrice,
            'cost_price' => $costPrice,
            'waste_rate' => $wasteRate,
            'formula_type' => $formulaType,
            'formula_params' => [],
            'note' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fixed(string $name, string $unit, int $unitPrice, int $costPrice): array
    {
        return [
            'name' => $name,
            'unit' => $unit,
            'unit_price' => $unitPrice,
            'cost_price' => $costPrice,
            'waste_rate' => 0,
            'formula_type' => 'fixed_quantity',
            'formula_params' => ['quantity' => 1],
            'note' => null,
        ];
    }
}
