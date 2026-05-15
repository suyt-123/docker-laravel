<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends StoreEquipmentRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['equipment_no'] = [
            'required',
            'string',
            'max:50',
            Rule::unique('equipment', 'equipment_no')->ignore($this->route('equipment')),
        ];

        return $rules;
    }
}
