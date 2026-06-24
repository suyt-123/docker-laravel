<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_no' => ['nullable', 'string', 'max:50', Rule::unique('projects', 'project_no')],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'work_crew_id' => ['nullable', 'integer', Rule::exists('work_crews', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'contract_amount' => ['nullable', 'integer', 'min:0'],
            'estimated_cost' => ['nullable', 'integer', 'min:0'],
            'actual_cost' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
