<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageEmployees() ?? false;
    }

    public function rules(): array
    {
        $employee = $this->route('employee');

        return [
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_number')->ignore($employee?->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee?->user_id),
                Rule::unique('employees', 'email')->ignore($employee?->id),
            ],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position' => ['nullable', 'string', 'max:120'],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'date_hired' => ['nullable', 'date'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($employee?->user_id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'account_status' => ['required', Rule::enum(AccountStatus::class)],
            'work_schedule_id' => ['nullable', 'exists:work_schedules,id'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
