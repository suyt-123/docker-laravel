<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryTransactionRequest extends FormRequest
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
            'material_id' => ['required', 'integer', Rule::exists('materials', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'purchase_order_item_id' => ['nullable', 'integer', Rule::exists('purchase_order_items', 'id')],
            'type' => ['required', 'string', Rule::in(['inbound', 'purchase_in', 'outbound', 'return', 'transfer', 'adjustment', 'waste'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:32'],
            'unit_cost' => ['nullable', 'integer', 'min:0'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
