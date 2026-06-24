<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends StoreProjectRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['project_no'] = [
            'required',
            'string',
            'max:50',
            Rule::unique('projects', 'project_no')->ignore($this->route('project')),
        ];

        return $rules;
    }
}
