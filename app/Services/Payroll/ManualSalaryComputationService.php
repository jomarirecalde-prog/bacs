<?php

namespace App\Services\Payroll;

use App\Enums\ManualSalaryComputationMode;
use App\Enums\ManualSalaryComputationStatus;
use App\Enums\ManualSalaryMonthlySubtype;
use App\Enums\ManualSalaryPayableIntent;
use App\Enums\PayrollPeriodStatus;
use App\Models\Employee;
use App\Models\ManualSalaryComputation;
use App\Models\ManualSalaryComputationLine;
use App\Models\ManualSalaryComputationOverride;
use App\Models\PayrollAttendanceSummary;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualSalaryComputationService
{
    public function __construct(
        private readonly PayrollEngine $engine,
        private readonly PayrollAttendanceAggregator $attendance,
        private readonly EmployeeSalaryService $salaries,
    ) {}

    public function createDraft(Employee $employee, array $data, User $actor): ManualSalaryComputation
    {
        $this->assertDuplicateAndOverlap($employee, $data);

        return DB::transaction(function () use ($employee, $data, $actor) {
            $computation = new ManualSalaryComputation;
            $this->fillHeaderFromRequest($computation, $employee, $data);
            $computation->employee_id = $employee->id;
            $computation->status = ManualSalaryComputationStatus::Draft;
            $computation->prepared_by = $actor->id;
            $computation->prepared_at = now();
            $computation->save();

            $this->applyManualLines($computation, $data['custom_lines'] ?? []);
            $this->recalculate($computation, $actor, $data['overrides'] ?? []);

            return $computation->fresh(['lines', 'overrides', 'payrollPeriod', 'employee.department']);
        });
    }

    public function updateDraft(ManualSalaryComputation $computation, array $data, User $actor): ManualSalaryComputation
    {
        $this->assertEditable($computation);
        $this->assertDuplicateAndOverlap($computation->employee, $data, $computation->id);

        return DB::transaction(function () use ($computation, $data, $actor) {
            $this->fillHeaderFromRequest($computation, $computation->employee, $data);
            $computation->save();

            $this->applyManualLines($computation, $data['custom_lines'] ?? [], keepExistingManual: false);
            $this->recalculate($computation, $actor, $data['overrides'] ?? []);

            return $computation->fresh(['lines', 'overrides', 'payrollPeriod', 'employee.department']);
        });
    }

    public function recalculate(ManualSalaryComputation $computation, User $actor, array $overrides = []): ManualSalaryComputation
    {
        if ($computation->status?->isLocked()) {
            throw ValidationException::withMessages([
                'status' => 'This computation is locked and cannot be recalculated.',
            ]);
        }

        $employee = $computation->employee()->firstOrFail();
        $period = $this->resolvePayrollPeriod($computation);
        $summary = $this->resolveAttendanceSummary($employee, $period, $computation->attendance_inputs ?? []);

        if ($computation->monthly_subtype === ManualSalaryMonthlySubtype::Summary) {
            return $this->recalculateMonthlySummary($computation, $employee, $actor);
        }

        $manualTotalBasic = $this->valueFromOverrides($overrides, 'total_basic_pay');
        $snapshot = $this->engine->computeEmployeeSnapshot(
            $period,
            $employee,
            $summary,
            $period->end_date->toDateString(),
            $manualTotalBasic,
        );

        $this->recordOverrides($computation, $overrides, $snapshot, $actor);
        $this->applyFieldOverridesToSnapshot($snapshot, $overrides);

        $computation->system_snapshot = $this->sanitizeSnapshotForStorage($snapshot);
        $this->syncSystemLines($computation, $snapshot);

        $manualEarnings = (float) $computation->lines()
            ->where('direction', 'earning')
            ->where('is_manual', true)
            ->sum('amount');
        $manualDeductions = (float) $computation->lines()
            ->where('direction', 'deduction')
            ->where('is_manual', true)
            ->sum('amount');

        $comp = $snapshot['compensation'];
        $attendanceDed = (float) $snapshot['absence_deduction']
            + (float) $snapshot['late_deduction']
            + (float) $snapshot['undertime_deduction'];

        $statutoryTotal = Money::toFloat(Money::round(array_sum(array_column($comp['statutory_and_loans'], 'amount'))));

        $grossWage = Money::toFloat(Money::round(
            (float) ($snapshot['total_basic_pay'] ?? 0)
            + (float) $snapshot['overtime_pay']
            + (float) $snapshot['holiday_pay']
            + (float) $snapshot['premium_pay']
        ));
        $deMinimis = (float) $comp['de_minimis'];
        $otherEarnings = Money::toFloat(Money::round((float) $comp['other_earnings'] + $manualEarnings));
        $grossCompensation = Money::toFloat(Money::round($grossWage + $deMinimis + $otherEarnings));
        $totalDeductions = Money::toFloat(Money::round($attendanceDed + $statutoryTotal + $manualDeductions));
        $netPay = Money::toFloat(Money::round($grossCompensation - $attendanceDed - $statutoryTotal - $manualDeductions));

        $attrs = $snapshot['payroll_employee_attributes'];
        $computation->fill([
            'salary_type' => $attrs['salary_type'],
            'basic_salary' => $attrs['basic_salary'],
            'monthly_salary' => $attrs['monthly_salary'],
            'semi_monthly_salary' => $attrs['semi_monthly_salary'],
            'daily_rate' => $attrs['daily_rate'],
            'hourly_rate' => $attrs['hourly_rate'],
            'working_hours_per_day' => $attrs['working_hours_per_day'],
            'basic_pay' => $snapshot['basic_pay'],
            'overtime_pay' => $snapshot['overtime_pay'],
            'holiday_pay' => $snapshot['holiday_pay'],
            'premium_pay' => $snapshot['premium_pay'],
            'de_minimis' => $deMinimis,
            'other_earnings' => $otherEarnings,
            'gross_wage' => $grossWage,
            'gross_compensation' => $grossCompensation,
            'attendance_deductions' => $attendanceDed,
            'statutory_deductions' => $statutoryTotal,
            'other_deductions' => $manualDeductions,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
            'computation_warnings' => $snapshot['computation_warnings'] ?? null,
        ]);
        $computation->save();

        return $computation->fresh(['lines', 'overrides']);
    }

    public function submit(ManualSalaryComputation $computation, User $actor): ManualSalaryComputation
    {
        $this->assertEditable($computation);
        $computation->update([
            'status' => ManualSalaryComputationStatus::ForReview,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
        ]);

        return $computation;
    }

    public function approve(ManualSalaryComputation $computation, User $actor, ?string $notes = null): ManualSalaryComputation
    {
        if ($computation->status !== ManualSalaryComputationStatus::ForReview) {
            throw ValidationException::withMessages(['status' => 'Only computations for review can be approved.']);
        }

        $computation->update([
            'status' => ManualSalaryComputationStatus::Approved,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        return $computation;
    }

    public function reject(ManualSalaryComputation $computation, User $actor, ?string $notes = null): ManualSalaryComputation
    {
        if ($computation->status !== ManualSalaryComputationStatus::ForReview) {
            throw ValidationException::withMessages(['status' => 'Only computations for review can be rejected.']);
        }

        $computation->update([
            'status' => ManualSalaryComputationStatus::Rejected,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        return $computation;
    }

    public function finalize(ManualSalaryComputation $computation, User $actor): ManualSalaryComputation
    {
        if ($computation->status !== ManualSalaryComputationStatus::Approved) {
            throw ValidationException::withMessages(['status' => 'Approve the computation before finalizing.']);
        }

        if ($computation->isPayable() && $computation->payable_intent === ManualSalaryPayableIntent::Standard) {
            $this->assertNoAutomatedPayrollConflict($computation);
        }

        $this->assertDuplicateAndOverlap(
            $computation->employee,
            [
                'computation_mode' => $computation->computation_mode->value,
                'payable_intent' => $computation->payable_intent->value,
                'monthly_subtype' => $computation->monthly_subtype?->value,
                'coverage_start' => $computation->coverage_start->toDateString(),
                'coverage_end' => $computation->coverage_end->toDateString(),
                'payroll_period_id' => $computation->payroll_period_id,
            ],
            $computation->id,
            finalizing: true,
        );

        $computation->update([
            'status' => ManualSalaryComputationStatus::Finalized,
            'finalized_by' => $actor->id,
            'finalized_at' => now(),
        ]);

        return $computation;
    }

    private function recalculateMonthlySummary(ManualSalaryComputation $computation, Employee $employee, User $actor): ManualSalaryComputation
    {
        $year = (int) $computation->payroll_year;
        $month = (int) $computation->payroll_month;
        $start = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $end = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $manualRows = ManualSalaryComputation::query()
            ->where('employee_id', $employee->id)
            ->where('status', ManualSalaryComputationStatus::Finalized)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('coverage_start', [$start, $end])
                    ->orWhereBetween('coverage_end', [$start, $end]);
            })
            ->get();

        $periodRows = PayrollEmployee::query()
            ->where('employee_id', $employee->id)
            ->whereHas('payrollPeriod', function ($q) use ($start, $end) {
                $q->whereIn('status', [PayrollPeriodStatus::Finalized->value, PayrollPeriodStatus::Paid->value])
                    ->where('start_date', '>=', $start)
                    ->where('end_date', '<=', $end);
            })
            ->get();

        $computation->lines()->where('is_system', true)->delete();

        $sort = 0;
        $gross = 0.0;
        $deductions = 0.0;
        $net = 0.0;

        foreach ($manualRows as $row) {
            $gross += (float) $row->gross_compensation;
            $deductions += (float) $row->total_deductions;
            $net += (float) $row->net_pay;
            $computation->lines()->create([
                'direction' => 'earning',
                'category' => 'summary',
                'code' => 'manual_'.$row->id,
                'label' => 'Manual computation #'.$row->id.' ('.$row->coverage_start->format('M j').' – '.$row->coverage_end->format('M j').')',
                'amount' => $row->gross_compensation,
                'is_system' => true,
                'sort_order' => $sort++,
            ]);
        }

        foreach ($periodRows as $row) {
            $gross += (float) $row->gross_compensation;
            $deductions += (float) $row->total_deductions;
            $net += (float) $row->net_pay;
            $computation->lines()->create([
                'direction' => 'earning',
                'category' => 'summary',
                'code' => 'payroll_'.$row->id,
                'label' => 'Processed payroll · '.$row->payrollPeriod?->period_name,
                'amount' => $row->gross_compensation,
                'is_system' => true,
                'sort_order' => $sort++,
            ]);
        }

        $computation->fill([
            'gross_compensation' => Money::toFloat(Money::round($gross)),
            'total_deductions' => Money::toFloat(Money::round($deductions)),
            'net_pay' => Money::toFloat(Money::round($net)),
            'gross_wage' => Money::toFloat(Money::round($gross)),
            'computation_warnings' => ['Monthly summary — not a separate payable payroll.'],
        ]);
        $computation->save();

        return $computation->fresh(['lines']);
    }

    private function fillHeaderFromRequest(ManualSalaryComputation $computation, Employee $employee, array $data): void
    {
        $mode = ManualSalaryComputationMode::from($data['computation_mode']);
        $coverage = $this->resolveCoverageDates($mode, $data);

        $computation->fill([
            'computation_mode' => $mode,
            'monthly_subtype' => isset($data['monthly_subtype']) ? ManualSalaryMonthlySubtype::from($data['monthly_subtype']) : null,
            'payable_intent' => ManualSalaryPayableIntent::from($data['payable_intent'] ?? ManualSalaryPayableIntent::Standard->value),
            'payroll_period_id' => $data['payroll_period_id'] ?? null,
            'coverage_start' => $coverage['start'],
            'coverage_end' => $coverage['end'],
            'payroll_year' => $coverage['year'],
            'payroll_month' => $coverage['month'],
            'attendance_inputs' => $data['attendance'] ?? [],
            'remarks' => $data['remarks'] ?? null,
        ]);
    }

    /**
     * @return array{start: string, end: string, year: ?int, month: ?int}
     */
    private function resolveCoverageDates(ManualSalaryComputationMode $mode, array $data): array
    {
        if ($mode === ManualSalaryComputationMode::Period) {
            if (! empty($data['payroll_period_id'])) {
                $period = PayrollPeriod::query()->findOrFail($data['payroll_period_id']);

                return [
                    'start' => $period->start_date->toDateString(),
                    'end' => $period->end_date->toDateString(),
                    'year' => (int) $period->end_date->format('Y'),
                    'month' => (int) $period->end_date->format('n'),
                ];
            }

            return [
                'start' => $data['coverage_start'],
                'end' => $data['coverage_end'],
                'year' => (int) Carbon::parse($data['coverage_end'])->format('Y'),
                'month' => (int) Carbon::parse($data['coverage_end'])->format('n'),
            ];
        }

        $year = (int) $data['payroll_year'];
        $month = (int) $data['payroll_month'];
        $subtype = ManualSalaryMonthlySubtype::from($data['monthly_subtype'] ?? ManualSalaryMonthlySubtype::FullMonth->value);

        if ($subtype === ManualSalaryMonthlySubtype::PartialMonth) {
            return [
                'start' => $data['coverage_start'],
                'end' => $data['coverage_end'],
                'year' => $year,
                'month' => $month,
            ];
        }

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'year' => $year,
            'month' => $month,
        ];
    }

    private function resolvePayrollPeriod(ManualSalaryComputation $computation): PayrollPeriod
    {
        if ($computation->payroll_period_id) {
            return $computation->payrollPeriod()->firstOrFail();
        }

        return new PayrollPeriod([
            'period_name' => 'Manual '.$computation->coverage_start->format('M j').' – '.$computation->coverage_end->format('M j, Y'),
            'start_date' => $computation->coverage_start,
            'end_date' => $computation->coverage_end,
            'status' => PayrollPeriodStatus::Draft,
        ]);
    }

    private function resolveAttendanceSummary(Employee $employee, PayrollPeriod $period, array $inputs): PayrollAttendanceSummary
    {
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();

        $summary = null;
        if ($period->id) {
            $summary = PayrollAttendanceSummary::query()
                ->where('payroll_period_id', $period->id)
                ->where('employee_id', $employee->id)
                ->first();

            if (! $summary) {
                $summary = $this->attendance->aggregateEmployee($employee, $period, $start, $end);
            }
        } else {
            $payload = $this->attendance->summarizeEmployeeDateRange($employee, $start, $end);
            $summary = new PayrollAttendanceSummary($payload + [
                'employee_id' => $employee->id,
            ]);
        }

        foreach ($inputs as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (in_array($key, $summary->getFillable(), true)) {
                $summary->{$key} = $value;
            }
        }

        return $summary;
    }

    /**
     * @param  list<array{direction: string, category?: string, label: string, amount: float|int|string, code?: string}>  $lines
     */
    private function applyManualLines(ManualSalaryComputation $computation, array $lines, bool $keepExistingManual = false): void
    {
        if (! $keepExistingManual) {
            $computation->lines()->where('is_manual', true)->delete();
        }

        $sort = (int) $computation->lines()->max('sort_order') + 1;
        foreach ($lines as $line) {
            if ((float) ($line['amount'] ?? 0) <= 0) {
                continue;
            }
            $computation->lines()->create([
                'direction' => $line['direction'],
                'category' => $line['category'] ?? 'custom',
                'code' => $line['code'] ?? null,
                'label' => $line['label'],
                'amount' => $line['amount'],
                'is_manual' => true,
                'sort_order' => $sort++,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function syncSystemLines(ManualSalaryComputation $computation, array $snapshot): void
    {
        $computation->lines()->where('is_system', true)->delete();

        $sort = 0;
        $earningLines = $snapshot['earning_lines'];
        $map = [
            'basic_pay' => ['Basic Pay', 'basic'],
            'overtime' => ['Overtime Pay', 'overtime'],
            'de_minimis' => ['Allowances / De Minimis', 'allowance'],
            'other_earnings' => ['Other Earnings', 'other'],
        ];

        foreach ($map as $code => [$label, $category]) {
            $amount = (float) ($earningLines[$code] ?? 0);
            if ($amount <= 0 && $code !== 'basic_pay') {
                continue;
            }
            $computation->lines()->create([
                'direction' => 'earning',
                'category' => $category,
                'code' => $code,
                'label' => $label,
                'amount' => $amount,
                'is_system' => true,
                'sort_order' => $sort++,
            ]);
        }

        if ((float) $snapshot['holiday_pay'] > 0) {
            $computation->lines()->create([
                'direction' => 'earning',
                'category' => 'holiday',
                'code' => 'holiday_pay',
                'label' => 'Holiday Pay',
                'amount' => $snapshot['holiday_pay'],
                'is_system' => true,
                'sort_order' => $sort++,
            ]);
        }

        if ((float) $snapshot['premium_pay'] > 0) {
            $computation->lines()->create([
                'direction' => 'earning',
                'category' => 'premium',
                'code' => 'premium_pay',
                'label' => 'Night Shift / Premium Pay',
                'amount' => $snapshot['premium_pay'],
                'is_system' => true,
                'sort_order' => $sort++,
            ]);
        }

        foreach (['absence' => 'Absence Deduction', 'late' => 'Tardiness Deduction', 'undertime' => 'Undertime Deduction'] as $code => $label) {
            $amount = (float) ($earningLines[$code] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $computation->lines()->create([
                'direction' => 'deduction',
                'category' => 'attendance',
                'code' => $code,
                'label' => $label,
                'amount' => $amount,
                'is_system' => true,
                'sort_order' => $sort++,
            ]);
        }

        foreach ($snapshot['compensation']['statutory_and_loans'] as $item) {
            if ((float) $item['amount'] <= 0) {
                continue;
            }
            $computation->lines()->create([
                'direction' => 'deduction',
                'category' => ($item['code'] ?? '') === 'tax' || in_array($item['code'], ['sss', 'philhealth', 'hdmf'], true)
                    ? 'statutory'
                    : 'loan',
                'code' => $item['code'],
                'label' => $item['label'],
                'amount' => $item['amount'],
                'is_system' => true,
                'sort_order' => $sort++,
            ]);
        }
    }

    /**
     * @param  list<array{field_key: string, manual_value: float|int|string, reason: string}>  $overrides
     * @param  array<string, mixed>  $snapshot
     */
    private function recordOverrides(ManualSalaryComputation $computation, array $overrides, array $snapshot, User $actor): void
    {
        foreach ($overrides as $override) {
            if (empty($override['reason'])) {
                throw ValidationException::withMessages([
                    'overrides' => 'Each manual override requires a reason.',
                ]);
            }

            $key = $override['field_key'];
            $original = $this->originalValueForField($key, $snapshot);
            $manual = (float) $override['manual_value'];

            ManualSalaryComputationOverride::query()->create([
                'computation_id' => $computation->id,
                'field_key' => $key,
                'original_value' => $original,
                'manual_value' => $manual,
                'reason' => $override['reason'],
                'user_id' => $actor->id,
            ]);
        }
    }

    /**
     * @param  list<array{field_key: string, manual_value: float|int|string, reason: string}>  $overrides
     * @param  array<string, mixed>  $snapshot
     */
    private function applyFieldOverridesToSnapshot(array &$snapshot, array $overrides): void
    {
        foreach ($overrides as $override) {
            $key = $override['field_key'];
            $value = (float) $override['manual_value'];

            match ($key) {
                'basic_pay' => $snapshot['basic_pay'] = $value,
                'overtime_pay' => $snapshot['overtime_pay'] = $value,
                'holiday_pay' => $snapshot['holiday_pay'] = $value,
                'premium_pay' => $snapshot['premium_pay'] = $value,
                'total_basic_pay' => $snapshot['total_basic_pay'] = $value,
                default => null,
            };
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function originalValueForField(string $key, array $snapshot): float
    {
        return match ($key) {
            'basic_pay' => (float) $snapshot['basic_pay'],
            'overtime_pay' => (float) $snapshot['overtime_pay'],
            'holiday_pay' => (float) $snapshot['holiday_pay'],
            'premium_pay' => (float) $snapshot['premium_pay'],
            'total_basic_pay' => (float) $snapshot['total_basic_pay'],
            default => 0.0,
        };
    }

    /**
     * @param  list<array{field_key: string, manual_value: float|int|string, reason: string}>  $overrides
     */
    private function valueFromOverrides(array $overrides, string $field): ?float
    {
        foreach ($overrides as $override) {
            if ($override['field_key'] === $field) {
                return (float) $override['manual_value'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function sanitizeSnapshotForStorage(array $snapshot): array
    {
        unset($snapshot['salary']);

        return $snapshot;
    }

    private function assertEditable(ManualSalaryComputation $computation): void
    {
        if (! $computation->status?->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or rejected computations can be edited.',
            ]);
        }
    }

    private function assertNoAutomatedPayrollConflict(ManualSalaryComputation $computation): void
    {
        if (! $computation->payroll_period_id) {
            return;
        }

        $period = $computation->payrollPeriod;
        if (! $period) {
            return;
        }

        if (! in_array($period->status, [PayrollPeriodStatus::Finalized, PayrollPeriodStatus::Paid], true)) {
            return;
        }

        $exists = PayrollEmployee::query()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $computation->employee_id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'payroll_period_id' => 'This employee already has processed payroll for this finalized/paid period. Use supplemental or adjustment intent instead.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertDuplicateAndOverlap(
        Employee $employee,
        array $data,
        ?int $ignoreId = null,
        bool $finalizing = false,
    ): void {
        $subtype = $data['monthly_subtype'] ?? null;
        if ($subtype === ManualSalaryMonthlySubtype::Summary->value) {
            return;
        }

        $intent = ManualSalaryPayableIntent::from($data['payable_intent'] ?? ManualSalaryPayableIntent::Standard->value);
        if ($intent !== ManualSalaryPayableIntent::Standard) {
            return;
        }

        $start = Carbon::parse($data['coverage_start'] ?? now())->toDateString();
        $end = Carbon::parse($data['coverage_end'] ?? now())->toDateString();

        $blocking = ManualSalaryComputationStatus::payableBlocking();

        $overlapManual = ManualSalaryComputation::query()
            ->where('employee_id', $employee->id)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('payable_intent', ManualSalaryPayableIntent::Standard)
            ->whereIn('status', array_map(fn ($s) => $s->value, $blocking))
            ->where(function ($q) {
                $q->whereNull('monthly_subtype')
                    ->orWhere('monthly_subtype', '!=', ManualSalaryMonthlySubtype::Summary->value);
            })
            ->where(function ($q) use ($start, $end) {
                $q->where('coverage_start', '<=', $end)
                    ->where('coverage_end', '>=', $start);
            })
            ->exists();

        if ($overlapManual) {
            throw ValidationException::withMessages([
                'coverage_start' => 'Another payable manual computation overlaps this coverage for the employee.',
            ]);
        }

        if (! empty($data['payroll_period_id'])) {
            $period = PayrollPeriod::query()->find($data['payroll_period_id']);
            if ($period && in_array($period->status, [PayrollPeriodStatus::Finalized, PayrollPeriodStatus::Paid], true)) {
                $hasPayroll = PayrollEmployee::query()
                    ->where('payroll_period_id', $period->id)
                    ->where('employee_id', $employee->id)
                    ->exists();

                if ($hasPayroll) {
                    throw ValidationException::withMessages([
                        'payroll_period_id' => 'Automated payroll already exists for this employee and period. Choose supplemental/adjustment or a different coverage.',
                    ]);
                }
            }
        }
    }
}
