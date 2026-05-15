<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quotation_no' => ['nullable', 'string', 'max:50', Rule::unique('quotations', 'quotation_no')],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'quotation_template_id' => ['nullable', 'integer', Rule::exists('quotation_templates', 'id')->where('status', 'active')],
            'template_inputs' => ['nullable', 'array'],
            'status' => ['required', 'string', 'max:50'],
            'profit_rate' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'tax' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'valid_until' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['nullable', 'integer', Rule::exists('materials', 'id')],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.spec' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['required', 'string', 'max:32'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.cost_price' => ['nullable', 'integer', 'min:0'],
            'items.*.waste_rate' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
