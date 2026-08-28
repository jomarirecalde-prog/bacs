<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveEntitlementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configurePolicy', \App\Models\LeaveBalance::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'types' => ['required', 'array'],
            'types.*.id' => ['required', 'integer', 'exists:leave_types,id'],
            'types.*.entitlement_days' => ['required', 'numeric', 'min:0', 'max:365'],
        ];
    }
}
