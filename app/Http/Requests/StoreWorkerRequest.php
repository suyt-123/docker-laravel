<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkerRequest extends FormRequest
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
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('workers', 'user_id')],
            'work_crew_id' => ['nullable', 'integer', Rule::exists('work_crews', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:100'],
            'daily_rate' => ['nullable', 'integer', 'min:0'],
            'certifications_text' => ['nullable', 'string', 'max:1000'],
            'insurance_expires_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
