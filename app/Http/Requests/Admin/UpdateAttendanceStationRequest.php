<?php

namespace App\Http\Requests\Admin;

use App\Enums\StationStatus;
use App\Models\AttendanceStation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $station = $this->route('station');

        return $station instanceof AttendanceStation
            && ($this->user()?->can('update', $station) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('station_code')) {
            $this->merge(['station_code' => strtoupper(trim((string) $this->input('station_code')))]);
        }
    }

    public function rules(): array
    {
        $station = $this->route('station');

        return [
            'station_name' => ['required', 'string', 'max:120'],
            'station_code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('attendance_stations', 'station_code')->ignore($station)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'location' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::enum(StationStatus::class)],
            'idle_timeout_minutes' => ['required', 'integer', Rule::in([0, 30, 60, 240])],
        ];
    }
}
