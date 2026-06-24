<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
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
            'purchase_order_no' => ['nullable', 'string', 'max:50', Rule::unique('purchase_orders', 'purchase_order_no')],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'status' => ['required', 'string', Rule::in(['draft', 'sent', 'partially_received', 'completed', 'cancelled'])],
            'ordered_date' => ['nullable', 'date'],
            'expected_date' => ['nullable', 'date'],
            'tax' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer', Rule::exists('materials', 'id')],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.spec' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['required', 'string', 'max:32'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
