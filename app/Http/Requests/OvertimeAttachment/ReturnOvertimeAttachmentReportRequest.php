<?php

namespace App\Http\Requests\OvertimeAttachment;

use Illuminate\Foundation\Http\FormRequest;

class ReturnOvertimeAttachmentReportRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
