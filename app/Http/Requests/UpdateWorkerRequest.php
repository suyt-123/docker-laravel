<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateWorkerRequest extends StoreWorkerRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('workers', 'user_id')->ignore($this->route('worker')),
            ],
        ];
    }
}
