<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialRecordRequest extends FormRequest
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
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'type' => ['required', 'string', Rule::in(['deposit', 'progress', 'final', 'change_order', 'reimbursement', 'deduction', 'other'])],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'paid_date' => ['nullable', 'date'],
            'status' => ['required', 'string', Rule::in(['pending', 'paid', 'overdue', 'cancelled'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
