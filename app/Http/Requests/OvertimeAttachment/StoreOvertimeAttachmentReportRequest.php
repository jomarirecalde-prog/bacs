<?php

namespace App\Http\Requests\OvertimeAttachment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOvertimeAttachmentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('overtimeAttachmentReport');

        if ($report) {
            return $this->user()?->can('update', $report) ?? false;
        }

        return $this->user()?->can('create', \App\Models\OvertimeAttachmentReport::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['draft', 'submit'])],
            'overtime_request_id' => ['nullable', 'integer', 'exists:overtime_requests,id'],
            'date_coverage_start' => ['nullable', 'date'],
            'date_coverage_end' => ['nullable', 'date', 'after_or_equal:date_coverage_start'],
            'date_submitted' => ['nullable', 'date'],
            'prepared_by' => ['nullable', 'string', 'max:255'],
            'approved_by' => ['nullable', 'string', 'max:255'],
            'activities' => ['nullable', 'array'],
            'activities.*.activity_description' => ['nullable', 'string', 'max:5000'],
            'activities.*.activity_date' => ['nullable', 'date'],
            'activities.*.time_from' => ['nullable', 'date_format:H:i'],
            'activities.*.time_to' => ['nullable', 'date_format:H:i'],
            'activities.*.status_remark' => ['nullable', 'string', 'max:500'],
            'activities.*.reviewed_vouched_by' => ['nullable', 'string', 'max:255'],
        ];
    }
}
