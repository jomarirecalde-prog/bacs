<?php

namespace App\Http\Requests\OvertimeAttachment;

use App\Enums\LeaveDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideOvertimeAttachmentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('overtimeAttachmentReport');

        return $report ? ($this->user()?->can('endorse', $report) ?? false) : false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([LeaveDecision::Approved->value, LeaveDecision::Denied->value])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
