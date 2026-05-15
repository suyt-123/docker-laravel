<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateEquipmentCategoryRequest extends StoreEquipmentCategoryRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['code'] = [
            'required',
            'string',
            'max:100',
            Rule::unique('equipment_categories', 'code')->ignore($this->route('equipment_category')),
        ];

        return $rules;
    }
}
