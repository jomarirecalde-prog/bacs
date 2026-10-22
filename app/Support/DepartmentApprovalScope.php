<?php

namespace App\Support;

use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalTransactionType;
use App\Models\ApprovalWorkflowAssignee;
use App\Models\ApprovalWorkflowConfiguration;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DepartmentApprovalScope
{
    /** @return list<int> */
    public static function departmentIdsForApprover(
        int $employeeId,
        ApprovalTransactionType $type,
        ?ApprovalAssigneeType $approvalType = null,
    ): array {
        $configId = ApprovalWorkflowConfiguration::query()
            ->where('transaction_type', $type->value)
            ->value('id');

        if (! $configId) {
            return [];
        }

        $query = ApprovalWorkflowAssignee::query()
            ->where('workflow_configuration_id', $configId)
            ->where('employee_id', $employeeId)
            ->where('is_active', true);

        if ($approvalType !== null) {
            $query->where('approval_type', $approvalType->value);
        }

        return $query->pluck('department_id')->filter()->unique()->values()->all();
    }

    public static function approverCanAccessRequestDepartment(User $user, ?int $requestDepartmentId): bool
    {
        if ($requestDepartmentId === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $employeeId = $user->employee?->id;
        if (! $employeeId) {
            return false;
        }

        return ApprovalWorkflowAssignee::query()
            ->where('employee_id', $employeeId)
            ->where('department_id', $requestDepartmentId)
            ->where('is_active', true)
            ->exists();
    }

    /** @param  Builder<\Illuminate\Database\Eloquent\Model>  $query */
    public static function constrainToApproverDepartments(
        Builder $query,
        User $user,
        ApprovalTransactionType $type,
        string $departmentColumn = 'department_id',
    ): void {
        if ($user->isAdmin()) {
            return;
        }

        $employeeId = $user->employee?->id;
        if (! $employeeId) {
            $query->whereRaw('1 = 0');

            return;
        }

        $departmentIds = self::departmentIdsForApprover($employeeId, $type);
        if ($departmentIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($departmentColumn, $departmentIds);
    }

    /** @param  Builder<\Illuminate\Database\Eloquent\Model>  $query */
    public static function constrainToEmployeeDepartments(
        Builder $query,
        User $user,
        ApprovalTransactionType $type,
    ): void {
        if ($user->isAdmin()) {
            return;
        }

        $employeeId = $user->employee?->id;
        if (! $employeeId) {
            $query->whereRaw('1 = 0');

            return;
        }

        $departmentIds = self::departmentIdsForApprover($employeeId, $type);
        if ($departmentIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('employee', fn (Builder $employee) => $employee->whereIn('department_id', $departmentIds));
    }
}
