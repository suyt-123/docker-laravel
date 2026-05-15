<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateQuotationRequest extends StoreQuotationRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['quotation_no'] = [
            'required',
            'string',
            'max:50',
            Rule::unique('quotations', 'quotation_no')->ignore($this->route('quotation')),
        ];

        return $rules;
    }
}
