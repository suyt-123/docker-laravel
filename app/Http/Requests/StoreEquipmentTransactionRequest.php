<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['check_out', 'check_in', 'assign_project', 'transfer_project', 'maintenance_in', 'maintenance_out', 'lost', 'retire', 'adjust'])],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'worker_id' => ['nullable', 'integer', Rule::exists('workers', 'id')],
            'work_crew_id' => ['nullable', 'integer', Rule::exists('work_crews', 'id')],
            'occurred_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'condition_after' => ['nullable', 'string', Rule::in(['good', 'fair', 'damaged', 'unsafe'])],
            'from_location' => ['nullable', 'string', 'max:255'],
            'to_location' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
