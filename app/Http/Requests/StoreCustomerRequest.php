<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'line_id' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'source' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:2000'],
            'primary_contact.name' => ['nullable', 'string', 'max:255'],
            'primary_contact.title' => ['nullable', 'string', 'max:100'],
            'primary_contact.phone' => ['nullable', 'string', 'max:50'],
            'primary_contact.email' => ['nullable', 'email', 'max:255'],
            'primary_contact.line_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
