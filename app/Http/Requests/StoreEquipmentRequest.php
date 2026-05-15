<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipment_no' => ['nullable', 'string', 'max:50', Rule::unique('equipment', 'equipment_no')],
            'equipment_category_id' => ['nullable', 'integer', Rule::exists('equipment_categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_no' => ['nullable', 'string', 'max:255'],
            'asset_tag' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['available', 'assigned', 'borrowed', 'maintenance', 'lost', 'retired'])],
            'condition' => ['required', 'string', Rule::in(['good', 'fair', 'damaged', 'unsafe'])],
            'current_project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'current_worker_id' => ['nullable', 'integer', Rule::exists('workers', 'id')],
            'current_work_crew_id' => ['nullable', 'integer', Rule::exists('work_crews', 'id')],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'integer', 'min:0'],
            'warranty_until' => ['nullable', 'date'],
            'last_maintenance_at' => ['nullable', 'date'],
            'next_maintenance_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
