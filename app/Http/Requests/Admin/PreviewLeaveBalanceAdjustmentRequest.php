<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewLeaveBalanceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('adjust', \App\Models\LeaveBalance::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'leave_type_code' => ['required', 'string', 'max:32'],
            'adjustment_kind' => ['required', Rule::in(['add', 'deduct', 'set_entitlement'])],
            'days' => ['required', 'numeric', 'min:0', 'max:365'],
        ];
    }
}
