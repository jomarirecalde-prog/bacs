<?php

namespace App\Http\Requests\Admin;

use App\Enums\AttendanceEffect;
use App\Enums\CalendarEventType;
use App\Enums\EventAudience;
use App\Enums\EventStatus;
use App\Models\CalendarEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof CalendarEvent
            ? ($this->user()?->can('update', $event) ?? false)
            : ($this->user()?->can('create', CalendarEvent::class) ?? false);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'event_type' => ['required', Rule::enum(CalendarEventType::class)],
            'description' => ['nullable', 'string', 'max:5000'],

            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_all_day' => ['boolean'],
            'start_time' => ['nullable', 'date_format:H:i,H:i:s', 'required_if:is_all_day,0'],
            'end_time' => ['nullable', 'date_format:H:i,H:i:s'],

            'location' => ['nullable', 'string', 'max:160'],
            'additional_instructions' => ['nullable', 'string', 'max:5000'],

            'audience_type' => ['required', Rule::enum(EventAudience::class)],
            'department_ids' => ['array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'employee_ids' => ['array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],

            'attendance_effect' => ['nullable', Rule::enum(AttendanceEffect::class)],
            'notify_audience' => ['boolean'],
            'status' => ['required', Rule::enum(EventStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
            'start_time.required_if' => 'A start time is required unless the event runs all day.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_all_day' => $this->boolean('is_all_day'),
            'notify_audience' => $this->boolean('notify_audience'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $audience = $this->enumValue(EventAudience::class, $this->input('audience_type'));
            $type = $this->enumValue(CalendarEventType::class, $this->input('event_type'));

            if ($audience === EventAudience::Departments && empty($this->input('department_ids'))) {
                $validator->errors()->add('department_ids', 'Select at least one department for this audience.');
            }

            if ($audience === EventAudience::Employees && empty($this->input('employee_ids'))) {
                $validator->errors()->add('employee_ids', 'Select at least one employee for this audience.');
            }

            if ($type?->supportsAttendanceEffect() && blank($this->input('attendance_effect'))) {
                $validator->errors()->add('attendance_effect', 'Choose how this day affects attendance.');
            }

            if (! $this->boolean('is_all_day')) {
                $start = $this->input('start_time');
                $end = $this->input('end_time');

                if (filled($start) && filled($end) && substr($end, 0, 5) <= substr($start, 0, 5)) {
                    $validator->errors()->add('end_time', 'The end time must be later than the start time.');
                }
            }
        });
    }

    /**
     * Normalised attributes ready for persisting. Keeps contradictory
     * combinations (timed fields on an all-day event, an attendance effect on a
     * meeting) out of the database.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();
        $type = $this->enumValue(CalendarEventType::class, $data['event_type']);
        $allDay = (bool) ($data['is_all_day'] ?? false);

        return [
            'title' => $data['title'],
            'event_type' => $data['event_type'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'is_all_day' => $allDay,
            'start_time' => $allDay ? null : ($data['start_time'] ?? null),
            'end_time' => $allDay ? null : ($data['end_time'] ?? null),
            'location' => $data['location'] ?? null,
            'additional_instructions' => $data['additional_instructions'] ?? null,
            'audience_type' => $data['audience_type'],
            'attendance_effect' => $type?->supportsAttendanceEffect()
                ? ($data['attendance_effect'] ?? null)
                : null,
            'notify_audience' => (bool) ($data['notify_audience'] ?? false),
            'status' => $data['status'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function departmentIds(): array
    {
        return $this->enumValue(EventAudience::class, $this->input('audience_type')) === EventAudience::Departments
            ? array_map('intval', $this->input('department_ids', []))
            : [];
    }

    /**
     * @return array<int, int>
     */
    public function employeeIds(): array
    {
        return $this->enumValue(EventAudience::class, $this->input('audience_type')) === EventAudience::Employees
            ? array_map('intval', $this->input('employee_ids', []))
            : [];
    }

    private function enumValue(string $enum, mixed $value): mixed
    {
        return is_string($value) || is_int($value) ? $enum::tryFrom($value) : null;
    }
}
