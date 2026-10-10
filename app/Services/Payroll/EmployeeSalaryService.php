<?php

namespace App\Services\Payroll;

use App\Enums\EmployeeSalaryStatus;
use App\Enums\SalaryType;
use App\Models\Employee;
use App\Models\EmployeeSalaryHistory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeSalaryService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function assign(Employee $employee, array $data, User $actor): EmployeeSalaryHistory
    {
        return DB::transaction(function () use ($employee, $data, $actor) {
            $effectiveFrom = $data['effective_from'];
            $this->assertNoOverlap($employee, $effectiveFrom, null);

            $this->closeActiveRecords($employee, $effectiveFrom);

            $record = EmployeeSalaryHistory::query()->create([
                'employee_id' => $employee->id,
                'designation_id' => $data['designation_id'] ?? $employee->designation_id,
                'salary_type' => $data['salary_type'],
                'basic_salary' => $data['basic_salary'] ?? null,
                'monthly_salary' => $data['monthly_salary'] ?? null,
                'semi_monthly_salary' => $data['semi_monthly_salary'] ?? null,
                'daily_rate' => $data['daily_rate'] ?? null,
                'hourly_rate' => $data['hourly_rate'] ?? null,
                'working_hours_per_day' => $data['working_hours_per_day'] ?? 8,
                'working_days_basis' => $data['working_days_basis'] ?? config('payroll.working_days_basis', 22),
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'status' => EmployeeSalaryStatus::Active,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->audit->log(
                $actor,
                'salary_assigned',
                'Payroll',
                $record->id,
                "Salary assignment for {$employee->fullName()} effective {$effectiveFrom}."
            );

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateHistory(EmployeeSalaryHistory $record, array $data, User $actor): EmployeeSalaryHistory
    {
        $record->update([
            'salary_type' => $data['salary_type'],
            'basic_salary' => $data['basic_salary'] ?? null,
            'monthly_salary' => $data['monthly_salary'] ?? null,
            'semi_monthly_salary' => $data['semi_monthly_salary'] ?? null,
            'daily_rate' => $data['daily_rate'] ?? null,
            'hourly_rate' => $data['hourly_rate'] ?? null,
            'working_hours_per_day' => $data['working_hours_per_day'] ?? $record->working_hours_per_day,
            'working_days_basis' => $data['working_days_basis'] ?? $record->working_days_basis,
            'notes' => $data['notes'] ?? $record->notes,
        ]);

        $record->loadMissing('employee');

        $this->audit->log(
            $actor,
            'salary_updated',
            'Payroll',
            $record->id,
            "Salary record #{$record->id} updated for {$record->employee->fullName()}."
        );

        return $record->fresh();
    }

    public function effectiveForDate(Employee $employee, string $date): ?EmployeeSalaryHistory
    {
        return EmployeeSalaryHistory::query()
            ->where('employee_id', $employee->id)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->whereIn('status', [
                EmployeeSalaryStatus::Active->value,
                EmployeeSalaryStatus::Superseded->value,
            ])
            ->orderByDesc('effective_from')
            ->first();
    }

    public function current(Employee $employee): ?EmployeeSalaryHistory
    {
        return EmployeeSalaryHistory::query()
            ->where('employee_id', $employee->id)
            ->where('status', EmployeeSalaryStatus::Active)
            ->orderByDesc('effective_from')
            ->first();
    }

    private function closeActiveRecords(Employee $employee, string $newEffectiveFrom): void
    {
        $previousDay = date('Y-m-d', strtotime($newEffectiveFrom.' -1 day'));

        EmployeeSalaryHistory::query()
            ->where('employee_id', $employee->id)
            ->where('status', EmployeeSalaryStatus::Active)
            ->each(function (EmployeeSalaryHistory $row) use ($previousDay, $newEffectiveFrom) {
                if ($row->effective_from->toDateString() >= $newEffectiveFrom) {
                    throw ValidationException::withMessages([
                        'effective_from' => 'A salary record already exists on or after this effective date.',
                    ]);
                }

                $row->update([
                    'effective_to' => $previousDay,
                    'status' => EmployeeSalaryStatus::Superseded,
                ]);
            });
    }

    private function assertNoOverlap(Employee $employee, string $from, ?int $ignoreId): void
    {
        $exists = EmployeeSalaryHistory::query()
            ->where('employee_id', $employee->id)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('status', EmployeeSalaryStatus::Active)
            ->where('effective_from', $from)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'effective_from' => 'An active salary assignment already starts on this date.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertPrimaryRatePresent(array $data): void
    {
        $type = SalaryType::from($data['salary_type']);
        $primary = match ($type) {
            SalaryType::Monthly => $data['monthly_salary'] ?? null,
            SalaryType::SemiMonthly => $data['semi_monthly_salary'] ?? null,
            SalaryType::Daily => $data['daily_rate'] ?? null,
            SalaryType::Hourly => $data['hourly_rate'] ?? null,
            SalaryType::FixedPeriod => $data['basic_salary'] ?? null,
        };

        if ($primary === null || $primary === '') {
            throw ValidationException::withMessages([
                'amount' => 'Enter the rate for the selected salary type (or use the primary amount field).',
            ]);
        }
    }

    /**
     * Normalize rate fields from salary type + primary amount input.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeAmounts(array $data): array
    {
        $type = SalaryType::from($data['salary_type']);
        $hours = (int) ($data['working_hours_per_day'] ?? 8);
        $basis = (int) ($data['working_days_basis'] ?? config('payroll.working_days_basis', 22));

        $amount = isset($data['amount']) && $data['amount'] !== '' ? (float) $data['amount'] : null;

        foreach (['basic_salary', 'monthly_salary', 'semi_monthly_salary', 'daily_rate', 'hourly_rate'] as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
                unset($data[$key]);
            } else {
                $data[$key] = (float) $data[$key];
            }
        }

        match ($type) {
            SalaryType::Monthly => $data['monthly_salary'] = $data['monthly_salary'] ?? $amount,
            SalaryType::SemiMonthly => $data['semi_monthly_salary'] = $data['semi_monthly_salary'] ?? $amount,
            SalaryType::Daily => $data['daily_rate'] = $data['daily_rate'] ?? $amount,
            SalaryType::Hourly => $data['hourly_rate'] = $data['hourly_rate'] ?? $amount,
            SalaryType::FixedPeriod => $data['basic_salary'] = $data['basic_salary'] ?? $amount,
        };

        if ($type === SalaryType::Monthly && isset($data['monthly_salary'])) {
            $monthly = (float) $data['monthly_salary'];
            $data['semi_monthly_salary'] ??= round($monthly / 2, 2);
            $data['daily_rate'] ??= $basis > 0 ? round($monthly / $basis, 2) : null;
            $data['hourly_rate'] ??= ($hours > 0 && isset($data['daily_rate']))
                ? round((float) $data['daily_rate'] / $hours, 4)
                : null;
        }

        if ($type === SalaryType::SemiMonthly && isset($data['semi_monthly_salary'])) {
            $semi = (float) $data['semi_monthly_salary'];
            $data['monthly_salary'] ??= round($semi * 2, 2);
            $semiDays = max(1, (int) floor($basis / 2));
            $data['daily_rate'] ??= round($semi / $semiDays, 2);
            $data['hourly_rate'] ??= ($hours > 0 && isset($data['daily_rate']))
                ? round((float) $data['daily_rate'] / $hours, 4)
                : null;
        }

        if ($type === SalaryType::Daily && isset($data['daily_rate']) && $hours > 0) {
            $data['hourly_rate'] ??= round((float) $data['daily_rate'] / $hours, 4);
        }

        return $data;
    }
}
