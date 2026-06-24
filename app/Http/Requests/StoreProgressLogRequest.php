<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgressLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $photoRules = config('features.progress_photos')
            ? ['nullable', 'array', 'max:10']
            : ['prohibited'];
        $photoFileRules = config('features.progress_photos')
            ? ['file', 'image', 'max:10240']
            : ['prohibited'];

        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'dispatch_id' => ['nullable', 'integer', Rule::exists('dispatches', 'id')],
            'worker_id' => ['nullable', 'integer', Rule::exists('workers', 'id')],
            'work_date' => ['required', 'date'],
            'weather' => ['nullable', 'string', 'max:50'],
            'worker_count' => ['nullable', 'integer', 'min:0', 'max:999'],
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'work_items' => ['nullable', 'string', 'max:3000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'issue' => ['nullable', 'string', 'max:5000'],
            'voice_text' => ['nullable', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:5000'],
            'photos' => $photoRules,
            'photos.*' => $photoFileRules,
        ];
    }
}
