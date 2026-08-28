<?php

namespace App\Http\Requests\Admin;

use App\Enums\LeaveParallelRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configure', \App\Models\LeaveApplication::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'workflows' => ['required', 'array'],
            'workflows.*.id' => ['required', 'integer', 'exists:leave_approval_workflows,id'],
            'workflows.*.parallel_rule' => ['required', Rule::enum(LeaveParallelRule::class)],
            'workflows.*.approvers' => ['nullable', 'array'],
            'workflows.*.approvers.immediate_supervisor' => ['nullable', 'array'],
            'workflows.*.approvers.immediate_supervisor.*' => ['integer', 'exists:users,id'],
            'workflows.*.approvers.department_head' => ['nullable', 'array'],
            'workflows.*.approvers.department_head.*' => ['integer', 'exists:users,id'],
            'workflows.*.approvers.administrative_head' => ['nullable', 'array'],
            'workflows.*.approvers.administrative_head.*' => ['integer', 'exists:users,id'],
            'workflows.*.approvers.hr_officer' => ['nullable', 'array'],
            'workflows.*.approvers.hr_officer.*' => ['integer', 'exists:users,id'],
        ];
    }
}
