<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'code')->ignore($this->route('role'))],
            'description' => ['nullable', 'string'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['integer', Rule::exists('capabilities', 'id')],
        ];
    }
}
