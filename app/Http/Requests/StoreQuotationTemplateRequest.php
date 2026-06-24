<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            'profit_rate' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'tax' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'parameter_definitions' => ['nullable', 'array'],
            'parameter_definitions.*.key' => ['required_with:parameter_definitions', 'string', 'max:80', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            'parameter_definitions.*.label' => ['required_with:parameter_definitions', 'string', 'max:100'],
            'parameter_definitions.*.unit' => ['nullable', 'string', 'max:32'],
            'parameter_definitions.*.default' => ['nullable', 'numeric'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['nullable', 'integer', Rule::exists('materials', 'id')],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.spec' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['required', 'string', 'max:32'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.cost_price' => ['nullable', 'integer', 'min:0'],
            'items.*.waste_rate' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'items.*.formula_type' => ['required', 'string', Rule::in(['fixed_quantity', 'area_based', 'length_based', 'panel_count', 'perimeter_based'])],
            'items.*.formula_params' => ['nullable', 'array'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
