<?php

namespace App\Http\Requests\Admin;

use App\Enums\LeaveParallelRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentLeaveWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configure', \App\Models\LeaveApplication::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'parallel_rule' => ['required', Rule::enum(LeaveParallelRule::class)],
            'is_active' => ['sometimes', 'boolean'],
            'activate' => ['sometimes', 'boolean'],
            'approvers' => ['nullable', 'array'],
            'approvers.immediate_supervisor' => ['nullable', 'array'],
            'approvers.immediate_supervisor.*' => ['integer', 'exists:users,id'],
            'approvers.department_head' => ['nullable', 'array'],
            'approvers.department_head.*' => ['integer', 'exists:users,id'],
            'approvers.administrative_head' => ['nullable', 'array'],
            'approvers.administrative_head.*' => ['integer', 'exists:users,id'],
        ];
    }
}
