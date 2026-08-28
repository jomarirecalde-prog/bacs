<?php

namespace App\Http\Requests\Leave;

use App\Enums\LeaveDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideLeaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application ? ($this->user()?->can('approve', $application) ?? false) : false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(LeaveDecision::class)],
            'reason' => ['nullable', 'string', 'max:2000', 'required_if:decision,'.LeaveDecision::Denied->value],
            'signature' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required_if' => 'A reason is required when denying a leave application.',
        ];
    }
}
