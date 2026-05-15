<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDispatchRequest extends FormRequest
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
            'work_crew_id' => ['nullable', 'integer', Rule::exists('work_crews', 'id')],
            'work_item' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:50'],
            'scheduled_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'address' => ['nullable', 'string', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'workers' => ['nullable', 'array'],
            'workers.*.id' => ['required_with:workers', 'integer', Rule::exists('workers', 'id')],
            'workers.*.hours' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'workers.*.wage' => ['nullable', 'integer', 'min:0'],
            'workers.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
