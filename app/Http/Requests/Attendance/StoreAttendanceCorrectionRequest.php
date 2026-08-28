<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendancePunchType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->employee !== null;
    }

    public function rules(): array
    {
        return [
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'punch_type' => ['required', Rule::enum(AttendancePunchType::class)],
            'requested_time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'attendance_date.before_or_equal' => 'Correction requests cannot be filed for future dates.',
            'reason.min' => 'Please provide a brief explanation (at least 10 characters).',
        ];
    }
}
