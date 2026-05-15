<?php

namespace App\Http\Requests;

use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRecordRequest extends FormRequest
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
        $photoRule = app(SettingService::class)->boolean('attendance.require_photo')
            ? ['required', 'file', 'image', 'max:10240']
            : ['nullable', 'file', 'image', 'max:10240'];

        return [
            'dispatch_id' => ['required', 'integer', Rule::exists('dispatches', 'id')],
            'worker_id' => ['nullable', 'integer', Rule::exists('workers', 'id')],
            'type' => ['required', 'string', Rule::in(['clock_in', 'clock_out'])],
            'recorded_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photo' => $photoRule,
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
