<?php

namespace App\Http\Requests\Leave;

use App\Enums\LeaveType;
use App\Enums\SpecialLeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\LeaveApplication::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'leave_type' => ['required', Rule::enum(LeaveType::class)],
            'special_leave_type' => ['nullable', Rule::enum(SpecialLeaveType::class), 'required_if:leave_type,'.LeaveType::Special->value],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'declaration_accepted' => ['accepted'],
            'employee_signature' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The ending date cannot be earlier than the starting date.',
            'declaration_accepted.accepted' => 'You must certify that the information provided is true and accurate.',
            'employee_signature.required' => 'Please sign the leave application before submitting.',
            'special_leave_type.required_if' => 'Select the applicable Special Leave type.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('leave_type') !== LeaveType::Special->value) {
            $this->merge(['special_leave_type' => null]);
        } elseif (! filled($this->input('special_leave_type'))) {
            $this->merge(['special_leave_type' => null]);
        }
    }
}
