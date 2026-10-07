<?php

namespace Database\Seeders;

use App\Enums\PayrollPeriodStatus;
use App\Enums\SalaryType;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeBenefit;
use App\Models\EmployeeDeduction;
use App\Models\PayrollDeductionType;
use App\Models\PayrollAttendanceSummary;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendanceCalculator;
use App\Services\Payroll\EmployeeSalaryService;
use App\Services\Payroll\OvertimeRequestSyncService;
use App\Services\Payroll\PayrollAttendanceAggregator;
use App\Services\Payroll\PayrollEngine;
use App\Support\ManilaTime;
use Illuminate\Database\Seeder;

/**
 * Seeds 30 employees with salary, DTR, and one computed payroll period (Sep 11–25, 2026).
 *
 * Run: php artisan db:seed --class=PayrollSampleDataSeeder
 * Or set SEED_SAMPLE_DATA=true (runs after SampleDataSeeder via DatabaseSeeder).
 */
class PayrollSampleDataSeeder extends Seeder
{
    public const PERIOD_START = '2026-09-11';

    public const PERIOD_END = '2026-09-25';

    public const SAMPLE_COUNT = 30;

    /**
     * Recurring deduction amounts are per semi-monthly cutoff (not monthly).
     * ALU (absence / late / undertime) is not listed here — it comes from DTR during compute.
     *
     * @var list<array{
     *     employee_number: string,
     *     semi_monthly: float,
     *     de_minimis: float,
     *     sss: float,
     *     philhealth: float,
     *     hdmf: float,
     *     tax: float,
     *     sss_loan?: float,
     *     hdmf_loan?: float,
     *     rcbc_loan?: float,
     *     notes: string
     * }>
     */
    public const SAMPLE_ROWS = [
        ['employee_number' => 'BACS-2026-0001', 'semi_monthly' => 75000.00, 'de_minimis' => 2000.00, 'sss' => 900.00, 'philhealth' => 1875.00, 'hdmf' => 100.00, 'tax' => 11250.00, 'notes' => 'Executive — full attendance'],
        ['employee_number' => 'BACS-2026-0002', 'semi_monthly' => 65000.00, 'de_minimis' => 2000.00, 'sss' => 900.00, 'philhealth' => 1625.00, 'hdmf' => 100.00, 'tax' => 9750.00, 'notes' => 'CFO'],
        ['employee_number' => 'BACS-2026-0003', 'semi_monthly' => 62000.00, 'de_minimis' => 1500.00, 'sss' => 877.50, 'philhealth' => 1550.00, 'hdmf' => 100.00, 'tax' => 9300.00, 'notes' => 'CTOO'],
        ['employee_number' => 'BACS-2026-0004', 'semi_monthly' => 58000.00, 'de_minimis' => 1500.00, 'sss' => 810.00, 'philhealth' => 1450.00, 'hdmf' => 100.00, 'tax' => 8700.00, 'notes' => 'CAO'],
        ['employee_number' => 'BACS-2026-0048', 'semi_monthly' => 55000.00, 'de_minimis' => 1500.00, 'sss' => 810.00, 'philhealth' => 1375.00, 'hdmf' => 100.00, 'tax' => 8250.00, 'notes' => 'CBDO / HR'],
        ['employee_number' => 'BACS-2026-0005', 'semi_monthly' => 12500.00, 'de_minimis' => 1000.00, 'sss' => 562.50, 'philhealth' => 312.50, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Admin — sample late pattern'],
        ['employee_number' => 'BACS-2026-0006', 'semi_monthly' => 11000.00, 'de_minimis' => 0, 'sss' => 495.00, 'philhealth' => 275.00, 'hdmf' => 50.00, 'tax' => 0.00, 'sss_loan' => 850.00, 'notes' => 'Field — one absent day + SSS loan'],
        ['employee_number' => 'BACS-2026-0007', 'semi_monthly' => 18000.00, 'de_minimis' => 1000.00, 'sss' => 630.00, 'philhealth' => 450.00, 'hdmf' => 50.00, 'tax' => 900.00, 'notes' => 'Finance — OT sample'],
        ['employee_number' => 'BACS-2026-0008', 'semi_monthly' => 14000.00, 'de_minimis' => 1000.00, 'sss' => 562.50, 'philhealth' => 350.00, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Finance assistant'],
        ['employee_number' => 'BACS-2026-0009', 'semi_monthly' => 11000.00, 'de_minimis' => 0, 'sss' => 495.00, 'philhealth' => 275.00, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Field staff'],
        ['employee_number' => 'BACS-2026-0010', 'semi_monthly' => 16000.00, 'de_minimis' => 1000.00, 'sss' => 607.50, 'philhealth' => 400.00, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Technical staff'],
        ['employee_number' => 'BACS-2026-0011', 'semi_monthly' => 22000.00, 'de_minimis' => 1000.00, 'sss' => 675.00, 'philhealth' => 550.00, 'hdmf' => 50.00, 'tax' => 1100.00, 'notes' => 'EHS head'],
        ['employee_number' => 'BACS-2026-0012', 'semi_monthly' => 10500.00, 'de_minimis' => 0, 'sss' => 472.50, 'philhealth' => 262.50, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Driver'],
        ['employee_number' => 'BACS-2026-0013', 'semi_monthly' => 15000.00, 'de_minimis' => 1000.00, 'sss' => 585.00, 'philhealth' => 375.00, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Accounting staff'],
        ['employee_number' => 'BACS-2026-0014', 'semi_monthly' => 20000.00, 'de_minimis' => 1000.00, 'sss' => 652.50, 'philhealth' => 500.00, 'hdmf' => 50.00, 'tax' => 1000.00, 'hdmf_loan' => 1200.00, 'notes' => 'PTL — Pag-IBIG loan'],
        ['employee_number' => 'BACS-2026-0015', 'semi_monthly' => 11500.00, 'de_minimis' => 0, 'sss' => 517.50, 'philhealth' => 287.50, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'GSS'],
        ['employee_number' => 'BACS-2026-0016', 'semi_monthly' => 11000.00, 'de_minimis' => 0, 'sss' => 495.00, 'philhealth' => 275.00, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Field staff'],
        ['employee_number' => 'BACS-2026-0017', 'semi_monthly' => 17000.00, 'de_minimis' => 1000.00, 'sss' => 630.00, 'philhealth' => 425.00, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Draftsman'],
        ['employee_number' => 'BACS-2026-0019', 'semi_monthly' => 21000.00, 'de_minimis' => 1000.00, 'sss' => 675.00, 'philhealth' => 525.00, 'hdmf' => 50.00, 'tax' => 1050.00, 'notes' => 'PTL — undertime sample'],
        ['employee_number' => 'BACS-2026-0020', 'semi_monthly' => 11000.00, 'de_minimis' => 0, 'sss' => 495.00, 'philhealth' => 275.00, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Field staff'],
        ['employee_number' => 'BACS-2026-0021', 'semi_monthly' => 19000.00, 'de_minimis' => 1000.00, 'sss' => 652.50, 'philhealth' => 475.00, 'hdmf' => 50.00, 'tax' => 950.00, 'rcbc_loan' => 2500.00, 'notes' => 'Junior engineer — RCBC loan'],
        ['employee_number' => 'BACS-2026-0023', 'semi_monthly' => 15500.00, 'de_minimis' => 1000.00, 'sss' => 585.00, 'philhealth' => 387.50, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Lab operator'],
        ['employee_number' => 'BACS-2026-0025', 'semi_monthly' => 24000.00, 'de_minimis' => 1500.00, 'sss' => 720.00, 'philhealth' => 600.00, 'hdmf' => 50.00, 'tax' => 1200.00, 'notes' => 'Project technical supervisor'],
        ['employee_number' => 'BACS-2026-0027', 'semi_monthly' => 13000.00, 'de_minimis' => 0, 'sss' => 540.00, 'philhealth' => 325.00, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Maintenance'],
        ['employee_number' => 'BACS-2026-0028', 'semi_monthly' => 11000.00, 'de_minimis' => 0, 'sss' => 495.00, 'philhealth' => 275.00, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Field staff'],
        ['employee_number' => 'BACS-2026-0029', 'semi_monthly' => 20500.00, 'de_minimis' => 1000.00, 'sss' => 675.00, 'philhealth' => 512.50, 'hdmf' => 50.00, 'tax' => 1025.00, 'notes' => 'PTL'],
        ['employee_number' => 'BACS-2026-0030', 'semi_monthly' => 16500.00, 'de_minimis' => 1000.00, 'sss' => 607.50, 'philhealth' => 412.50, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Bookkeeper'],
        ['employee_number' => 'BACS-2026-0031', 'semi_monthly' => 20000.00, 'de_minimis' => 1000.00, 'sss' => 652.50, 'philhealth' => 500.00, 'hdmf' => 50.00, 'tax' => 1000.00, 'notes' => 'PTL'],
        ['employee_number' => 'BACS-2026-0032', 'semi_monthly' => 12000.00, 'de_minimis' => 0, 'sss' => 517.50, 'philhealth' => 300.00, 'hdmf' => 50.00, 'tax' => 0.00, 'notes' => 'Admin staff'],
        ['employee_number' => 'BACS-2026-0033', 'semi_monthly' => 19800.00, 'de_minimis' => 1000.00, 'sss' => 652.50, 'philhealth' => 495.00, 'hdmf' => 50.00, 'tax' => 990.00, 'sss_loan' => 500.00, 'hdmf_loan' => 800.00, 'notes' => 'PTL — mixed OT/late + loans'],
    ];

    public function run(): void
    {
        $admin = User::query()->where('username', 'admin')->first();
        $schedule = WorkSchedule::defaultSchedule();

        if (! $admin || ! $schedule) {
            $this->command?->error('Run php artisan db:seed first (admin user and schedule required).');

            return;
        }

        $salaryService = app(EmployeeSalaryService::class);
        $designations = Designation::query()->pluck('id', 'designation_name');
        $deductionTypes = PayrollDeductionType::query()
            ->whereIn('code', ['sss', 'philhealth', 'hdmf', 'tax', 'sss_loan', 'hdmf_loan', 'rcbc_loan'])
            ->pluck('id', 'code');
        $effectiveFrom = '2026-01-01';
        $sampleEmployeeIds = [];

        foreach (self::SAMPLE_ROWS as $index => $row) {
            $employee = Employee::query()->where('employee_number', $row['employee_number'])->first();
            if (! $employee) {
                $this->command?->warn("Skipping missing employee {$row['employee_number']}");

                continue;
            }

            $sampleEmployeeIds[] = $employee->id;

            $designationId = $this->resolveDesignationId($employee, $designations);
            if ($designationId) {
                $employee->update(['designation_id' => $designationId]);
            }

            if (! $salaryService->current($employee)) {
                $payload = $salaryService->normalizeAmounts([
                    'salary_type' => SalaryType::SemiMonthly->value,
                    'amount' => $row['semi_monthly'],
                    'effective_from' => $effectiveFrom,
                    'designation_id' => $designationId,
                    'notes' => 'PayrollSampleDataSeeder: '.$row['notes'],
                ]);
                $payload['effective_from'] = $effectiveFrom;
                $salaryService->assign($employee, $payload, $admin);
            }

            if ($row['de_minimis'] > 0) {
                EmployeeBenefit::query()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'benefit_code' => 'de_minimis',
                    ],
                    [
                        'label' => 'De Minimis',
                        'amount' => $row['de_minimis'],
                        'effective_from' => $effectiveFrom,
                        'is_active' => true,
                    ]
                );
            }

            $this->seedRecurringDeductions($employee, $row, $deductionTypes, $effectiveFrom);

            $this->seedAttendanceForEmployee($employee, $schedule, $admin, $index);
        }

        $period = PayrollPeriod::query()->firstOrCreate(
            [
                'start_date' => self::PERIOD_START,
                'end_date' => self::PERIOD_END,
            ],
            [
                'period_name' => 'Sample Sep 11–25, 2026',
                'payroll_date' => '2026-09-30',
                'status' => PayrollPeriodStatus::Draft,
                'created_by' => $admin->id,
            ]
        );

        PayrollAttendanceSummary::query()
            ->where('payroll_period_id', $period->id)
            ->whereNotIn('employee_id', $sampleEmployeeIds)
            ->delete();

        PayrollEmployee::query()
            ->where('payroll_period_id', $period->id)
            ->whereNotIn('employee_id', $sampleEmployeeIds)
            ->delete();

        app(OvertimeRequestSyncService::class)->syncPeriod($period);
        $aggregator = app(PayrollAttendanceAggregator::class);
        Employee::query()->whereIn('id', $sampleEmployeeIds)->orderBy('id')->each(
            fn (Employee $employee) => $aggregator->aggregateEmployee($employee, $period)
        );
        app(PayrollEngine::class)->computePeriod($period);
        $period->refresh()->update(['status' => PayrollPeriodStatus::ForReview]);

        $count = $period->payrollEmployees()->whereIn('employee_id', $sampleEmployeeIds)->count();

        $this->command?->info("Payroll sample data ready: {$count} register rows for period {$period->period_name}.");
        $this->command?->line('  • Admin → Payroll → Payroll periods → open “Sample Sep 11–25, 2026”.');
        $this->command?->line('  • Reference CSV: docs/payroll-sample-data.csv');
        $this->command?->line('  • Re-run safe: skips employees who already have active salary.');
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $deductionTypes
     * @param  array<string, mixed>  $row
     */
    private function seedRecurringDeductions(Employee $employee, array $row, $deductionTypes, string $effectiveFrom): void
    {
        $map = [
            'sss' => 'sss',
            'philhealth' => 'philhealth',
            'hdmf' => 'hdmf',
            'tax' => 'tax',
            'sss_loan' => 'sss_loan',
            'hdmf_loan' => 'hdmf_loan',
            'rcbc_loan' => 'rcbc_loan',
        ];

        foreach ($map as $rowKey => $code) {
            $amount = (float) ($row[$rowKey] ?? 0);
            $typeId = $deductionTypes[$code] ?? null;

            if (! $typeId || $amount <= 0) {
                continue;
            }

            EmployeeDeduction::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'deduction_type_id' => $typeId,
                ],
                [
                    'amount' => $amount,
                    'effective_from' => $effectiveFrom,
                    'is_active' => true,
                    'notes' => 'PayrollSampleDataSeeder',
                ]
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $designations
     */
    private function resolveDesignationId(Employee $employee, $designations): ?int
    {
        $position = (string) $employee->position;

        foreach ($designations as $name => $id) {
            if (stripos($position, (string) $name) !== false) {
                return $id;
            }
        }

        $fallbacks = [
            'Field' => 'Field Staff',
            'Engineer' => 'Junior Office Engineer',
            'Driver' => 'Company Driver',
            'Bookkeeper' => 'Bookkeeper',
            'Admin' => 'Admin Assistant',
            'Technical' => 'Technical Staff',
            'Leader' => 'Project Team Leader',
            'Supervisor' => 'Project Technical Supervisor',
            'Lab' => 'Laboratory Operator',
            'Sales' => 'Technical Staff',
        ];

        foreach ($fallbacks as $needle => $designationName) {
            if (stripos($position, $needle) !== false && isset($designations[$designationName])) {
                return $designations[$designationName];
            }
        }

        return $designations['Technical Staff'] ?? $designations->first();
    }

    private function seedAttendanceForEmployee(Employee $employee, WorkSchedule $schedule, User $admin, int $index): void
    {
        $calculator = app(AttendanceCalculator::class);
        $absentDate = '2026-09-16';

        foreach ($this->weekdaysInPeriod() as $dayIndex => $date) {
            if ($index % 7 === 1 && $date === $absentDate) {
                continue;
            }

            $late = ($index + $dayIndex) % 5 === 1 ? 25 : 0;
            $ut = ($index + $dayIndex) % 9 === 0 ? 20 : 0;
            $otOut = ($index + $dayIndex) % 6 === 2 ? '18:00:00' : '17:00:00';

            $pmIn = '13:00:00';
            $pmOut = $ut > 0 ? '16:40:00' : $otOut;

            $punches = [
                'am_time_in' => ManilaTime::combineDateAndTime($date, $late > 0 ? '08:25:00' : '08:00:00'),
                'am_time_out' => ManilaTime::combineDateAndTime($date, '12:00:00'),
                'pm_time_in' => ManilaTime::combineDateAndTime($date, $pmIn),
                'pm_time_out' => ManilaTime::combineDateAndTime($date, $pmOut),
                'overtime_in' => null,
            ];

            $computed = $calculator->calculateFromPunches($date, $punches, $schedule, $employee->id);

            $record = \App\Models\Attendance::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                ],
                array_merge($computed, $punches, [
                    'status' => $computed['status']->value,
                    'remarks' => 'Payroll sample DTR',
                    'is_manual' => true,
                    'created_by' => $admin->id,
                ])
            );
            $record->syncLegacyFields();
            $record->save();
        }
    }

    /** @return list<string> */
    private function weekdaysInPeriod(): array
    {
        $dates = [];
        $cursor = ManilaTime::parse(self::PERIOD_START);
        $end = ManilaTime::parse(self::PERIOD_END);

        while ($cursor->lte($end)) {
            if ($cursor->isoWeekday() <= 5) {
                $dates[] = $cursor->toDateString();
            }
            $cursor = $cursor->addDay();
        }

        return $dates;
    }
}
