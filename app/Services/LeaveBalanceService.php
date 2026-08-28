<?php

namespace App\Services;

use App\Enums\LeaveBalanceAdjustmentType;
use App\Enums\LeaveType;
use App\Enums\SpecialLeaveType;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeaveTypeRecord;
use App\Models\User;
use App\Support\ManilaTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveBalanceService
{
    public function codeFor(LeaveType $type, ?SpecialLeaveType $special = null): string
    {
        return $type === LeaveType::Special && $special ? $special->value : $type->value;
    }

    public function defaultEntitlement(string $code): float
    {
        $fallback = LeaveType::tryFrom($code)?->defaultDays()
            ?? SpecialLeaveType::tryFrom($code)?->defaultDays()
            ?? 0;

        return (float) LeaveTypeRecord::entitlementFor($code, (int) $fallback);
    }

    /** @deprecated Use defaultEntitlement() for policy templates; balances are per employee. */
    public function entitlement(string $code): float
    {
        return $this->defaultEntitlement($code);
    }

    public function activeTypeCodes(): array
    {
        $fromDb = LeaveTypeRecord::query()->active()->orderBy('sort_order')->pluck('code')->all();

        if ($fromDb !== []) {
            return $fromDb;
        }

        return collect(LeaveType::cases())
            ->reject(fn (LeaveType $type) => $type === LeaveType::Special)
            ->map->value
            ->merge(collect(SpecialLeaveType::cases())->map->value)
            ->all();
    }

    public function forEmployee(Employee $employee, string $code, ?int $year = null): LeaveBalance
    {
        $year ??= (int) ManilaTime::now()->year;

        return LeaveBalance::query()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_type_code' => $code,
                'year' => $year,
            ],
            [
                'entitled_days' => $this->defaultEntitlement($code),
                'used_days' => 0,
            ]
        );
    }

    public function initializeForEmployee(Employee $employee, ?int $year = null, ?User $actor = null): void
    {
        $year ??= (int) ManilaTime::now()->year;

        DB::transaction(function () use ($employee, $year, $actor) {
            foreach ($this->activeTypeCodes() as $code) {
                if ($code === LeaveType::Special->value) {
                    continue;
                }

                $existing = LeaveBalance::query()
                    ->where('employee_id', $employee->id)
                    ->where('leave_type_code', $code)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    continue;
                }

                $entitlement = $this->defaultEntitlement($code);
                $balance = LeaveBalance::query()->create([
                    'employee_id' => $employee->id,
                    'leave_type_code' => $code,
                    'year' => $year,
                    'entitled_days' => $entitlement,
                    'used_days' => 0,
                ]);

                $this->recordAdjustment(
                    employee: $employee,
                    balance: $balance,
                    action: LeaveBalanceAdjustmentType::InitialEntitlement,
                    previousEntitlement: 0,
                    newEntitlement: $entitlement,
                    previousBalance: 0,
                    adjustmentDays: $entitlement,
                    newBalance: $entitlement,
                    reason: 'Initial entitlement from company default policy.',
                    effectiveDate: ManilaTime::todayDate(),
                    actor: $actor,
                    authorizedByName: $actor?->name,
                );
            }
        });
    }

    public function remaining(Employee $employee, string $code, ?int $year = null): float
    {
        return $this->forEmployee($employee, $code, $year)->remaining();
    }

    public function snapshot(Employee $employee, ?int $year = null): array
    {
        $year ??= (int) ManilaTime::now()->year;
        $out = [];

        foreach ($this->activeTypeCodes() as $code) {
            if ($code === LeaveType::Special->value) {
                continue;
            }

            $balance = $this->forEmployee($employee, $code, $year);
            $out[$code] = $this->rowFromBalance($balance);
        }

        return $out;
    }

    public function rowFromBalance(LeaveBalance $balance): array
    {
        return [
            'code' => $balance->leave_type_code,
            'entitled' => (float) $balance->entitled_days,
            'used' => (float) $balance->used_days,
            'remaining' => $balance->remaining(),
            'updated_at' => $balance->updated_at,
        ];
    }

    /**
     * @return array{previous_entitlement: float, new_entitlement: float, previous_balance: float, adjustment_days: float, new_balance: float}
     */
    public function previewManualAdjustment(
        Employee $employee,
        string $code,
        string $adjustmentKind,
        float $days,
        ?int $year = null
    ): array {
        $balance = $this->forEmployee($employee, $code, $year);
        $previousEntitlement = (float) $balance->entitled_days;
        $previousBalance = $balance->remaining();

        return match ($adjustmentKind) {
            'add' => [
                'previous_entitlement' => $previousEntitlement,
                'new_entitlement' => $previousEntitlement + $days,
                'previous_balance' => $previousBalance,
                'adjustment_days' => $days,
                'new_balance' => max(0, $previousBalance + $days),
            ],
            'deduct' => [
                'previous_entitlement' => $previousEntitlement,
                'new_entitlement' => $previousEntitlement,
                'previous_balance' => $previousBalance,
                'adjustment_days' => -$days,
                'new_balance' => max(0, $previousBalance - $days),
            ],
            'set_entitlement' => [
                'previous_entitlement' => $previousEntitlement,
                'new_entitlement' => $days,
                'previous_balance' => $previousBalance,
                'adjustment_days' => $days - $previousEntitlement,
                'new_balance' => max(0, $days - (float) $balance->used_days),
            ],
            default => throw ValidationException::withMessages([
                'adjustment_kind' => 'Invalid adjustment type.',
            ]),
        };
    }

    public function applyManualAdjustment(
        Employee $employee,
        User $actor,
        string $code,
        string $adjustmentKind,
        float $days,
        string $reason,
        string $effectiveDate,
        ?string $authorizedByName = null,
        ?int $year = null
    ): LeaveBalanceAdjustment {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for manual adjustments.']);
        }

        $year ??= (int) ManilaTime::now()->year;

        return DB::transaction(function () use ($employee, $actor, $code, $adjustmentKind, $days, $reason, $effectiveDate, $authorizedByName, $year) {
            $balance = LeaveBalance::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_code', $code)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = $this->forEmployee($employee, $code, $year);
                $balance = LeaveBalance::query()->whereKey($balance->id)->lockForUpdate()->first();
            }

            $preview = $this->previewManualAdjustment($employee, $code, $adjustmentKind, $days, $year);
            $action = match ($adjustmentKind) {
                'add' => LeaveBalanceAdjustmentType::ManualAddition,
                'deduct' => LeaveBalanceAdjustmentType::ManualDeduction,
                'set_entitlement' => LeaveBalanceAdjustmentType::EntitlementCorrection,
            };

            if ($adjustmentKind === 'add') {
                $balance->entitled_days = $preview['new_entitlement'];
            } elseif ($adjustmentKind === 'deduct') {
                $balance->used_days = (float) $balance->used_days + $days;
            } else {
                $balance->entitled_days = $preview['new_entitlement'];
            }

            $balance->save();

            return $this->recordAdjustment(
                employee: $employee,
                balance: $balance,
                action: $action,
                previousEntitlement: $preview['previous_entitlement'],
                newEntitlement: (float) $balance->entitled_days,
                previousBalance: $preview['previous_balance'],
                adjustmentDays: $preview['adjustment_days'],
                newBalance: $balance->remaining(),
                reason: $reason,
                effectiveDate: $effectiveDate,
                actor: $actor,
                authorizedByName: $authorizedByName ?: $actor->name,
            );
        });
    }

    public function deduct(Employee $employee, string $code, float $days, ?int $year = null): LeaveBalance
    {
        $balance = $this->forEmployee($employee, $code, $year);
        $balance->used_days = (float) $balance->used_days + $days;
        $balance->save();

        return $balance;
    }

    public function deductForApplication(
        LeaveApplication $application,
        User $actor,
        float $days,
        ?int $year = null
    ): ?LeaveBalanceAdjustment {
        if ($days <= 0) {
            return null;
        }

        $year ??= (int) $application->start_date->year;
        $code = $application->balanceCode();
        $employee = $application->employee;

        return DB::transaction(function () use ($application, $actor, $days, $year, $code, $employee) {
            $already = LeaveBalanceAdjustment::query()
                ->where('leave_application_id', $application->id)
                ->where('action_type', LeaveBalanceAdjustmentType::ApprovedLeaveDeduction)
                ->lockForUpdate()
                ->exists();

            if ($already) {
                return null;
            }

            $balance = LeaveBalance::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_code', $code)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = $this->forEmployee($employee, $code, $year);
                $balance = LeaveBalance::query()->whereKey($balance->id)->lockForUpdate()->first();
            }

            $previousBalance = $balance->remaining();
            $previousEntitlement = (float) $balance->entitled_days;
            $balance->used_days = (float) $balance->used_days + $days;
            $balance->save();

            return $this->recordAdjustment(
                employee: $employee,
                balance: $balance,
                action: LeaveBalanceAdjustmentType::ApprovedLeaveDeduction,
                previousEntitlement: $previousEntitlement,
                newEntitlement: $previousEntitlement,
                previousBalance: $previousBalance,
                adjustmentDays: -$days,
                newBalance: $balance->remaining(),
                reason: "Approved leave {$application->application_number}.",
                effectiveDate: $application->start_date->toDateString(),
                actor: $actor,
                authorizedByName: $actor->name,
                leaveApplicationId: $application->id,
            );
        });
    }

    public function restore(Employee $employee, string $code, float $days, ?int $year = null): LeaveBalance
    {
        $balance = $this->forEmployee($employee, $code, $year);
        $balance->used_days = max(0, (float) $balance->used_days - $days);
        $balance->save();

        return $balance;
    }

    public function restoreForApplication(
        LeaveApplication $application,
        User $actor,
        float $days,
        ?int $year = null
    ): ?LeaveBalanceAdjustment {
        if ($days <= 0) {
            return null;
        }

        $year ??= (int) $application->start_date->year;
        $code = $application->balanceCode();
        $employee = $application->employee;

        return DB::transaction(function () use ($application, $actor, $days, $year, $code, $employee) {
            $balance = LeaveBalance::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_code', $code)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                return null;
            }

            $previousBalance = $balance->remaining();
            $previousEntitlement = (float) $balance->entitled_days;
            $balance->used_days = max(0, (float) $balance->used_days - $days);
            $balance->save();

            return $this->recordAdjustment(
                employee: $employee,
                balance: $balance,
                action: LeaveBalanceAdjustmentType::LeaveReversal,
                previousEntitlement: $previousEntitlement,
                newEntitlement: $previousEntitlement,
                previousBalance: $previousBalance,
                adjustmentDays: $days,
                newBalance: $balance->remaining(),
                reason: "Leave reversal for {$application->application_number}.",
                effectiveDate: ManilaTime::todayDate(),
                actor: $actor,
                authorizedByName: $actor->name,
                leaveApplicationId: $application->id,
            );
        });
    }

    public function lastUpdatedAt(Employee $employee, ?int $year = null): ?\Illuminate\Support\Carbon
    {
        $year ??= (int) ManilaTime::now()->year;

        return LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->max('updated_at');
    }

    private function recordAdjustment(
        Employee $employee,
        LeaveBalance $balance,
        LeaveBalanceAdjustmentType $action,
        float $previousEntitlement,
        float $newEntitlement,
        float $previousBalance,
        float $adjustmentDays,
        float $newBalance,
        string $reason,
        string $effectiveDate,
        ?User $actor = null,
        ?string $authorizedByName = null,
        ?int $leaveApplicationId = null,
    ): LeaveBalanceAdjustment {
        return LeaveBalanceAdjustment::query()->create([
            'employee_id' => $employee->id,
            'leave_balance_id' => $balance->id,
            'leave_type_code' => $balance->leave_type_code,
            'year' => $balance->year,
            'action_type' => $action,
            'previous_entitlement' => $previousEntitlement,
            'new_entitlement' => $newEntitlement,
            'previous_balance' => $previousBalance,
            'adjustment_days' => $adjustmentDays,
            'new_balance' => $newBalance,
            'reason' => $reason,
            'effective_date' => $effectiveDate,
            'leave_application_id' => $leaveApplicationId,
            'updated_by' => $actor?->id,
            'authorized_by_name' => $authorizedByName,
            'recorded_at' => ManilaTime::now(),
        ]);
    }
}
