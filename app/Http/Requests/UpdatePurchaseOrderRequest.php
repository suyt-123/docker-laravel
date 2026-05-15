<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdatePurchaseOrderRequest extends StorePurchaseOrderRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['purchase_order_no'] = [
            'required',
            'string',
            'max:50',
            Rule::unique('purchase_orders', 'purchase_order_no')->ignore($this->route('purchase_order')),
        ];

        return $rules;
    }
}
