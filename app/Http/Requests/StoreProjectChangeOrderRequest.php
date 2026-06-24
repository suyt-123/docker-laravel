<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectChangeOrderRequest extends FormRequest
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
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'quotation_id' => ['nullable', 'integer', Rule::exists('quotations', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'integer', 'min:0'],
            'requires_formal_quotation' => ['nullable', 'boolean'],
            'requested_date' => ['nullable', 'date'],
            'approved_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', 'string', Rule::in(['draft', 'pending_approval', 'approved', 'customer_confirmed', 'converted', 'cancelled'])],
            'customer_note' => ['nullable', 'string', 'max:3000'],
            'internal_note' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
