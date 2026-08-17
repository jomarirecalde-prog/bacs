<?php

namespace App\Http\Requests\Admin;

use App\Enums\StationStatus;
use App\Models\AttendanceStation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AttendanceStation::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('station_code')) {
            $this->merge(['station_code' => strtoupper(trim((string) $this->input('station_code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'station_name' => ['required', 'string', 'max:120'],
            'station_code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9\-]+$/', 'unique:attendance_stations,station_code'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'location' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::enum(StationStatus::class)],
            'idle_timeout_minutes' => ['required', 'integer', Rule::in([0, 30, 60, 240])],
        ];
    }

    public function messages(): array
    {
        return [
            'station_code.regex' => 'Station ID may only contain letters, numbers, and hyphens.',
            'station_code.unique' => 'This Station ID is already in use.',
        ];
    }
}
