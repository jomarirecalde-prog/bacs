<?php

namespace App\Services\Payroll;

use App\Enums\PayrollComputationStatus;
use App\Enums\SalaryType;
use App\Models\Employee;
use App\Models\EmployeeBenefit;
use App\Models\EmployeeDeduction;
use App\Models\PayrollAttendanceSummary;
use App\Models\PayrollAdjustment;
use App\Models\PayrollDeductionType;
use App\Models\PayrollEarningType;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Support\Money;
use App\Support\PayrollSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollEngine
{
    public function __construct(
        private readonly EmployeeSalaryService $salaries,
        private readonly PayrollPremiumCalculator $premiums,
        private readonly SssContributionService $sss,
        private readonly WithholdingTaxService $withholdingTax,
    ) {}

    /**
     * @return array{employees: int, warnings: int, errors: int}
     */
    public function computePeriod(PayrollPeriod $period): array
    {
        $end = $period->end_date->toDateString();
        $warnings = 0;
        $errors = 0;
        $count = 0;

        $summaries = PayrollAttendanceSummary::query()
            ->where('payroll_period_id', $period->id)
            ->with('employee.department', 'employee.designation')
            ->get();

        if ($summaries->isEmpty()) {
            return ['employees' => 0, 'warnings' => 0, 'errors' => 0];
        }

        DB::transaction(function () use ($summaries, $period, $end, &$warnings, &$errors, &$count) {
            foreach ($summaries as $summary) {
                $employee = $summary->employee;
                if (! $employee) {
                    continue;
                }

                $result = $this->computeEmployee($period, $employee, $summary, $end);
                $count++;
                if ($result->computation_status === PayrollComputationStatus::Warning) {
                    $warnings++;
                }
                if ($result->computation_status === PayrollComputationStatus::Error) {
                    $errors++;
                }
            }
        });

        return ['employees' => $count, 'warnings' => $warnings, 'errors' => $errors];
    }

    public function computeEmployee(
        PayrollPeriod $period,
        Employee $employee,
        PayrollAttendanceSummary $summary,
        ?string $effectiveDate = null
    ): PayrollEmployee {
        $effectiveDate ??= $period->end_date->toDateString();
        $warnings = $summary->warnings ?? [];
        $salary = $this->salaries->effectiveForDate($employee, $effectiveDate);

        if (! $salary) {
            $warnings[] = 'No salary configuration for this period';
        }

        $status = $warnings === [] ? PayrollComputationStatus::Ok : PayrollComputationStatus::Warning;
        if (! $salary) {
            $status = PayrollComputationStatus::Error;
        }

        $employee->loadMissing(['department', 'designation']);

        $rates = $this->resolveRates($salary);
        $minuteRate = $this->minuteRate($rates);

        $basicPay = $this->basicPay($salary, $summary, $rates);
        $absenceDed = Money::toFloat(Money::round((float) $summary->absent_days * $rates['daily_rate']));
        $lateDed = Money::toFloat(Money::round($summary->late_minutes * $minuteRate));
        $undertimeDed = Money::toFloat(Money::round($summary->undertime_minutes * $minuteRate));
        $attendanceDed = $absenceDed + $lateDed + $undertimeDed;

        $computedTotalBasicPay = max(0, Money::toFloat(Money::round($basicPay - $attendanceDed)));

        $existing = PayrollEmployee::query()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        $totalBasicPay = $this->resolveTotalBasicPayForCompute($existing, $computedTotalBasicPay, $warnings, $status);

        $otMultiplier = PayrollSettings::overtimeMultiplier();
        $overtimePay = Money::toFloat(Money::round((float) $summary->approved_ot_hours * $rates['hourly_rate'] * $otMultiplier));
        $premium = $this->premiums->calculate($employee, $period, $rates['hourly_rate'], $rates['daily_rate']);
        $holidayPay = $premium['holiday_pay'];
        $premiumPay = $premium['premium_pay'];

        $compensation = $this->buildCompensationTotals(
            $totalBasicPay,
            $overtimePay,
            $holidayPay,
            $premiumPay,
            $employee->id,
            $period,
            $effectiveDate,
            $salary,
            $attendanceDed,
        );

        $payrollEmployee = PayrollEmployee::query()->updateOrCreate(
            [
                'payroll_period_id' => $period->id,
                'employee_id' => $employee->id,
            ],
            [
                'employee_number' => $employee->employee_number,
                'employee_name' => $employee->fullName(),
                'department_id' => $employee->department_id,
                'department_name' => $employee->department?->name,
                'designation_id' => $employee->designation_id ?? $salary?->designation_id,
                'designation_name' => $employee->designation?->designation_name ?? $employee->position,
                'salary_type' => $salary?->salary_type,
                'basic_salary' => $salary?->basic_salary,
                'monthly_salary' => $salary?->monthly_salary,
                'semi_monthly_salary' => $salary?->semi_monthly_salary,
                'daily_rate' => $rates['daily_rate'],
                'hourly_rate' => $rates['hourly_rate'],
                'working_hours_per_day' => $rates['working_hours'],
                'basic_pay' => $basicPay,
                'absence_deduction' => $absenceDed,
                'late_deduction' => $lateDed,
                'undertime_deduction' => $undertimeDed,
                'total_basic_pay' => $totalBasicPay,
                'computed_total_basic_pay' => $computedTotalBasicPay,
                'total_basic_pay_manually_set' => PayrollSettings::manualTotalBasicPay()
                    ? (bool) ($existing?->total_basic_pay_manually_set)
                    : false,
                'overtime_pay' => $overtimePay,
                'holiday_pay' => $holidayPay,
                'premium_pay' => $premiumPay,
                'gross_wage' => $compensation['gross_wage'],
                'de_minimis' => $compensation['de_minimis'],
                'other_earnings' => $compensation['other_earnings'],
                'gross_compensation' => $compensation['gross_compensation'],
                'total_deductions' => $compensation['total_deductions'],
                'net_pay' => $compensation['net_pay'],
                'computation_status' => $status,
                'computation_warnings' => $warnings === [] ? null : $warnings,
                'computed_at' => now(),
            ]
        );

        $earningLines = [
            'basic_pay' => $basicPay,
            'overtime' => $overtimePay,
            'de_minimis' => $compensation['de_minimis'],
            'absence' => $absenceDed,
            'late' => $lateDed,
            'undertime' => $undertimeDed,
        ];
        if ($compensation['other_earnings'] > 0) {
            $earningLines['other_earnings'] = $compensation['other_earnings'];
        }

        $this->syncLineItems($payrollEmployee, $earningLines, $compensation['statutory_and_loans'], $summary, $minuteRate, $otMultiplier, $premium);

        return $payrollEmployee->fresh(['earnings', 'deductions']);
    }

    public function applyManualTotalBasicPay(PayrollEmployee $payrollEmployee, float $amount, User $actor): PayrollEmployee
    {
        $payrollEmployee->loadMissing('payrollPeriod', 'employee');
        $period = $payrollEmployee->payrollPeriod;

        if ($period->isLocked()) {
            throw ValidationException::withMessages([
                'total_basic_pay' => 'This payroll period is locked; total basic pay cannot be changed.',
            ]);
        }

        if (! PayrollSettings::manualTotalBasicPay()) {
            throw ValidationException::withMessages([
                'total_basic_pay' => 'Manual total basic pay is disabled in payroll configuration.',
            ]);
        }

        $employee = $payrollEmployee->employee;
        if (! $employee) {
            throw ValidationException::withMessages([
                'total_basic_pay' => 'Employee record is missing for this payroll row.',
            ]);
        }

        $summary = PayrollAttendanceSummary::query()
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        if (! $summary) {
            throw ValidationException::withMessages([
                'total_basic_pay' => 'Run attendance compute for this period before entering total basic pay.',
            ]);
        }

        $effectiveDate = $period->end_date->toDateString();
        $salary = $this->salaries->effectiveForDate($employee, $effectiveDate);
        $attendanceDed = (float) $payrollEmployee->absence_deduction
            + (float) $payrollEmployee->late_deduction
            + (float) $payrollEmployee->undertime_deduction;

        $totalBasicPay = max(0, Money::toFloat(Money::round($amount)));

        $compensation = $this->buildCompensationTotals(
            $totalBasicPay,
            (float) $payrollEmployee->overtime_pay,
            (float) $payrollEmployee->holiday_pay,
            (float) $payrollEmployee->premium_pay,
            $employee->id,
            $period,
            $effectiveDate,
            $salary,
            $attendanceDed,
        );

        $warnings = $payrollEmployee->computation_warnings ?? [];
        $warnings = array_values(array_filter(
            $warnings,
            fn ($message) => $message !== 'Enter total basic pay manually'
        ));

        $status = $payrollEmployee->computation_status ?? PayrollComputationStatus::Ok;
        if ($salary === null) {
            $status = PayrollComputationStatus::Error;
        } elseif ($warnings !== []) {
            $status = PayrollComputationStatus::Warning;
        } else {
            $status = PayrollComputationStatus::Ok;
        }

        $payrollEmployee->update([
            'total_basic_pay' => $totalBasicPay,
            'total_basic_pay_manually_set' => true,
            'gross_wage' => $compensation['gross_wage'],
            'de_minimis' => $compensation['de_minimis'],
            'other_earnings' => $compensation['other_earnings'],
            'gross_compensation' => $compensation['gross_compensation'],
            'total_deductions' => $compensation['total_deductions'],
            'net_pay' => $compensation['net_pay'],
            'computation_status' => $status,
            'computation_warnings' => $warnings === [] ? null : $warnings,
            'computed_at' => now(),
        ]);

        $rates = $this->resolveRates($salary);
        $minuteRate = $this->minuteRate($rates);
        $otMultiplier = PayrollSettings::overtimeMultiplier();

        $earningLines = [
            'basic_pay' => (float) $payrollEmployee->basic_pay,
            'overtime' => (float) $payrollEmployee->overtime_pay,
            'de_minimis' => $compensation['de_minimis'],
            'absence' => (float) $payrollEmployee->absence_deduction,
            'late' => (float) $payrollEmployee->late_deduction,
            'undertime' => (float) $payrollEmployee->undertime_deduction,
        ];
        if ($compensation['other_earnings'] > 0) {
            $earningLines['other_earnings'] = $compensation['other_earnings'];
        }

        $premium = [
            'holiday_pay' => (float) $payrollEmployee->holiday_pay,
            'premium_pay' => (float) $payrollEmployee->premium_pay,
            'breakdown' => [],
        ];

        $this->syncLineItems(
            $payrollEmployee,
            $earningLines,
            $compensation['statutory_and_loans'],
            $summary,
            $minuteRate,
            $otMultiplier,
            $premium,
        );

        return $payrollEmployee->fresh(['earnings', 'deductions']);
    }

    /**
     * @param  list<string>  $warnings
     */
    private function resolveTotalBasicPayForCompute(
        ?PayrollEmployee $existing,
        float $computedTotalBasicPay,
        array &$warnings,
        PayrollComputationStatus &$status,
    ): float {
        if (! PayrollSettings::manualTotalBasicPay()) {
            return $computedTotalBasicPay;
        }

        if ($existing?->total_basic_pay_manually_set) {
            return max(0, (float) $existing->total_basic_pay);
        }

        $warnings[] = 'Enter total basic pay manually';
        if ($status === PayrollComputationStatus::Ok) {
            $status = PayrollComputationStatus::Warning;
        }

        return 0.0;
    }

    /**
     * @return array{
     *     gross_wage: float,
     *     de_minimis: float,
     *     other_earnings: float,
     *     gross_compensation: float,
     *     total_deductions: float,
     *     net_pay: float,
     *     statutory_and_loans: list<array{code: string, label: string, amount: float, deduction_type_id: ?int}>
     * }
     */
    private function buildCompensationTotals(
        float $totalBasicPay,
        float $overtimePay,
        float $holidayPay,
        float $premiumPay,
        int $employeeId,
        PayrollPeriod $period,
        string $effectiveDate,
        ?\App\Models\EmployeeSalaryHistory $salary,
        float $attendanceDed,
    ): array {
        $grossWage = Money::toFloat(Money::round($totalBasicPay + $overtimePay + $holidayPay + $premiumPay));

        $deMinimis = $this->deMinimisForPeriod($employeeId, $period);
        $adjustments = $this->periodAdjustments($employeeId, $period->id);
        $otherEarnings = Money::toFloat(Money::round($adjustments['earnings']));
        $adjustmentDeductions = $adjustments['deductions'];
        $grossCompensation = Money::toFloat(Money::round($grossWage + $deMinimis + $otherEarnings));

        $statutoryAndLoans = $this->recurringDeductions($employeeId, $effectiveDate);
        $monthlyComp = (float) ($salary?->monthly_salary ?? 0);
        if ($monthlyComp <= 0 && $salary?->semi_monthly_salary) {
            $monthlyComp = (float) $salary->semi_monthly_salary * 2;
        }
        $statutoryAndLoans = $this->applyAutoStatutory($statutoryAndLoans, $grossCompensation, $monthlyComp, $effectiveDate);
        $statutoryAndLoans = array_merge($statutoryAndLoans, $adjustmentDeductions);
        $statutoryTotal = Money::toFloat(Money::round(array_sum(array_column($statutoryAndLoans, 'amount'))));
        $totalDeductions = Money::toFloat(Money::round($attendanceDed + $statutoryTotal));
        $netPay = Money::toFloat(Money::round($grossCompensation - $statutoryTotal));

        return [
            'gross_wage' => $grossWage,
            'de_minimis' => $deMinimis,
            'other_earnings' => $otherEarnings,
            'gross_compensation' => $grossCompensation,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
            'statutory_and_loans' => $statutoryAndLoans,
        ];
    }

    /**
     * @return array{daily_rate: float, hourly_rate: float, working_hours: int}
     */
    private function resolveRates(?\App\Models\EmployeeSalaryHistory $salary): array
    {
        $hours = (int) ($salary?->working_hours_per_day ?: 8);
        $daily = (float) ($salary?->daily_rate ?? 0);
        $hourly = (float) ($salary?->hourly_rate ?? 0);

        if ($daily <= 0 && $hourly > 0) {
            $daily = $hourly * $hours;
        }
        if ($hourly <= 0 && $daily > 0 && $hours > 0) {
            $hourly = $daily / $hours;
        }
        if ($daily <= 0 && $salary?->monthly_salary) {
            $basis = (int) ($salary->working_days_basis ?: PayrollSettings::workingDaysBasis());
            $daily = $basis > 0 ? (float) $salary->monthly_salary / $basis : 0;
            $hourly = $hours > 0 ? $daily / $hours : 0;
        }

        return [
            'daily_rate' => max(0, $daily),
            'hourly_rate' => max(0, $hourly),
            'working_hours' => $hours,
        ];
    }

    private function minuteRate(array $rates): float
    {
        if (config('payroll.formulas.minute_rate_from') === 'daily' && $rates['working_hours'] > 0) {
            return $rates['daily_rate'] / $rates['working_hours'] / 60;
        }

        return $rates['hourly_rate'] / 60;
    }

    private function basicPay(?\App\Models\EmployeeSalaryHistory $salary, PayrollAttendanceSummary $summary, array $rates): float
    {
        if (! $salary) {
            return 0.0;
        }

        return match ($salary->salary_type) {
            SalaryType::Daily => Money::toFloat(Money::round((float) $summary->scheduled_days * $rates['daily_rate'])),
            SalaryType::Hourly => Money::toFloat(Money::round((float) $summary->regular_hours * $rates['hourly_rate'])),
            SalaryType::SemiMonthly => Money::toFloat(Money::round((float) ($salary->semi_monthly_salary ?? 0))),
            SalaryType::Monthly => Money::toFloat(Money::round((float) ($salary->semi_monthly_salary ?? ($salary->monthly_salary ? (float) $salary->monthly_salary / 2 : 0)))),
            SalaryType::FixedPeriod => Money::toFloat(Money::round((float) ($salary->basic_salary ?? 0))),
        };
    }

    /**
     * @return array{earnings: float, deductions: list<array{code: string, label: string, amount: float, deduction_type_id: ?int}>}
     */
    private function periodAdjustments(int $employeeId, int $periodId): array
    {
        $earnings = 0.0;
        $deductions = [];

        $rows = PayrollAdjustment::query()
            ->where('employee_id', $employeeId)
            ->where('target_payroll_period_id', $periodId)
            ->where('status', 'approved')
            ->get();

        foreach ($rows as $row) {
            $amount = Money::toFloat(Money::round($row->adjustment_amount));
            if ($amount <= 0) {
                continue;
            }
            if ($row->direction === 'deduction') {
                $deductions[] = [
                    'code' => 'retro_'.$row->id,
                    'label' => 'Retro: '.$row->adjustment_type,
                    'amount' => $amount,
                    'deduction_type_id' => null,
                ];
            } else {
                $earnings += $amount;
            }
        }

        return ['earnings' => $earnings, 'deductions' => $deductions];
    }

    private function deMinimisForPeriod(int $employeeId, PayrollPeriod $period): float
    {
        $end = $period->end_date->toDateString();

        $amount = EmployeeBenefit::query()
            ->where('employee_id', $employeeId)
            ->where('is_active', true)
            ->where('benefit_code', 'de_minimis')
            ->where(function ($q) use ($end) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $end);
            })
            ->where(function ($q) use ($end) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $end);
            })
            ->sum('amount');

        return Money::toFloat(Money::round($amount));
    }

    /**
     * @return list<array{code: string, label: string, amount: float, deduction_type_id: ?int}>
     */
    private function recurringDeductions(int $employeeId, string $asOf): array
    {
        $rows = EmployeeDeduction::query()
            ->where('employee_id', $employeeId)
            ->where('is_active', true)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $asOf);
            })
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $asOf);
            })
            ->with('deductionType:id,code,name')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $code = $row->deductionType?->code ?? 'other_deduction';
            $items[] = [
                'code' => $code,
                'label' => $row->deductionType?->name ?? 'Deduction',
                'amount' => Money::toFloat(Money::round($row->amount)),
                'deduction_type_id' => $row->deduction_type_id,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array{code: string, label: string, amount: float, deduction_type_id: ?int}>  $items
     * @return list<array{code: string, label: string, amount: float, deduction_type_id: ?int}>
     */
    private function applyAutoStatutory(array $items, float $grossCompensation, float $monthlyCompensation, string $asOfDate): array
    {
        $codes = array_column($items, 'code');
        $types = PayrollDeductionType::query()->pluck('id', 'code');

        if (PayrollSettings::sssFromBracketTable() && ! in_array('sss', $codes, true) && $monthlyCompensation > 0) {
            $sssAmount = $this->sss->semiMonthlyEmployeeShare($monthlyCompensation, $asOfDate);
            if ($sssAmount > 0) {
                $items[] = [
                    'code' => 'sss',
                    'label' => 'SSS (auto)',
                    'amount' => $sssAmount,
                    'deduction_type_id' => $types['sss'] ?? null,
                ];
                $codes[] = 'sss';
            }
        }

        $phRate = PayrollSettings::philhealthRate();
        if ($phRate > 0 && ! in_array('philhealth', $codes, true) && $grossCompensation > 0) {
            $items[] = [
                'code' => 'philhealth',
                'label' => 'PhilHealth (auto)',
                'amount' => Money::toFloat(Money::round($grossCompensation * $phRate / 100)),
                'deduction_type_id' => $types['philhealth'] ?? null,
            ];
        }

        $hdmf = PayrollSettings::hdmfAmount();
        if ($hdmf > 0 && ! in_array('hdmf', $codes, true)) {
            $items[] = [
                'code' => 'hdmf',
                'label' => 'HDMF (auto)',
                'amount' => Money::toFloat(Money::round($hdmf)),
                'deduction_type_id' => $types['hdmf'] ?? null,
            ];
        }

        if (PayrollSettings::taxFromBracketTable() && $monthlyCompensation > 0 && ! in_array('tax', $codes, true)) {
            $tax = $this->withholdingTax->semiMonthlyTax($monthlyCompensation, $asOfDate);
            if ($tax > 0) {
                $items[] = [
                    'code' => 'tax',
                    'label' => 'Withholding tax (auto)',
                    'amount' => $tax,
                    'deduction_type_id' => $types['tax'] ?? null,
                ];
            }
        }

        return $items;
    }

    /**
     * @param  array<string, float>  $earnings
     * @param  list<array{code: string, label: string, amount: float, deduction_type_id: ?int}>  $deductions
     */
    /**
     * @param  array{holiday_pay: float, premium_pay: float, breakdown: list<mixed>}  $premium
     */
    private function syncLineItems(
        PayrollEmployee $payrollEmployee,
        array $earnings,
        array $deductions,
        PayrollAttendanceSummary $summary,
        float $minuteRate,
        float $otMultiplier,
        array $premium = ['holiday_pay' => 0, 'premium_pay' => 0, 'breakdown' => []]
    ): void {
        $payrollEmployee->earnings()->delete();
        $payrollEmployee->deductions()->delete();

        $earningTypes = PayrollEarningType::query()->pluck('id', 'code');
        $deductionTypes = PayrollDeductionType::query()->pluck('id', 'code');

        if ($earnings['basic_pay'] > 0) {
            $payrollEmployee->earnings()->create([
                'earning_type_id' => $earningTypes['basic_pay'] ?? null,
                'code' => 'basic_pay',
                'label' => 'Basic Pay',
                'amount' => $earnings['basic_pay'],
                'meta' => ['scheduled_days' => $summary->scheduled_days],
            ]);
        }

        if ($earnings['overtime'] > 0) {
            $payrollEmployee->earnings()->create([
                'earning_type_id' => $earningTypes['overtime'] ?? null,
                'code' => 'overtime',
                'label' => 'Overtime',
                'amount' => $earnings['overtime'],
                'meta' => [
                    'hours' => $summary->approved_ot_hours,
                    'multiplier' => $otMultiplier,
                ],
            ]);
        }

        if ($earnings['de_minimis'] > 0) {
            $payrollEmployee->earnings()->create([
                'earning_type_id' => $earningTypes['de_minimis'] ?? null,
                'code' => 'de_minimis',
                'label' => 'De Minimis',
                'amount' => $earnings['de_minimis'],
            ]);
        }

        if (($earnings['other_earnings'] ?? 0) > 0) {
            $payrollEmployee->earnings()->create([
                'earning_type_id' => $earningTypes['other_earnings'] ?? null,
                'code' => 'other_earnings',
                'label' => 'Adjustments / Other',
                'amount' => $earnings['other_earnings'],
            ]);
        }

        if (($premium['holiday_pay'] ?? 0) > 0) {
            $payrollEmployee->earnings()->create([
                'earning_type_id' => $earningTypes['holiday_pay'] ?? null,
                'code' => 'holiday_pay',
                'label' => 'Holiday Pay',
                'amount' => $premium['holiday_pay'],
                'meta' => ['breakdown' => $premium['breakdown'] ?? []],
            ]);
        }

        if (($premium['premium_pay'] ?? 0) > 0) {
            $payrollEmployee->earnings()->create([
                'earning_type_id' => $earningTypes['premium_pay'] ?? null,
                'code' => 'premium_pay',
                'label' => 'Premium Pay',
                'amount' => $premium['premium_pay'],
            ]);
        }

        foreach (['absence' => $earnings['absence'], 'late' => $earnings['late'], 'undertime' => $earnings['undertime']] as $code => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $payrollEmployee->deductions()->create([
                'deduction_type_id' => $deductionTypes[$code] ?? null,
                'code' => $code,
                'label' => ucfirst($code),
                'amount' => $amount,
                'meta' => $code === 'late' || $code === 'undertime'
                    ? ['minutes' => $code === 'late' ? $summary->late_minutes : $summary->undertime_minutes, 'minute_rate' => $minuteRate]
                    : ['days' => $summary->absent_days],
            ]);
        }

        foreach ($deductions as $item) {
            if ($item['amount'] <= 0) {
                continue;
            }
            $payrollEmployee->deductions()->create([
                'deduction_type_id' => $item['deduction_type_id'],
                'code' => $item['code'],
                'label' => $item['label'],
                'amount' => $item['amount'],
            ]);
        }
    }
}
