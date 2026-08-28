<?php

namespace App\Http\Requests\Leave;

use App\Enums\LeaveDecision;
use App\Enums\LeavePaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessLeaveHrRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application ? ($this->user()?->can('processHr', $application) ?? false) : false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(LeaveDecision::class)],
            'payment_type' => ['required', Rule::enum(LeavePaymentType::class)],
            'reason' => ['nullable', 'string', 'max:2000', 'required_if:decision,'.LeaveDecision::Denied->value],
            'hr_remarks' => ['nullable', 'string', 'max:2000'],
            'hr_sil_as_of' => ['nullable', 'date'],
            'signature' => ['nullable', 'string'],
        ];
    }
}
