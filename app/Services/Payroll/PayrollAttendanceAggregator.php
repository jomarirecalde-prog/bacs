<?php

namespace App\Services\Payroll;

use App\Enums\AttendanceStatus;
use App\Enums\LeavePaymentType;
use App\Enums\LeaveStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Enums\OvertimeRequestStatus;
use App\Models\OvertimeRequest;
use App\Models\PayrollAttendanceSummary;
use App\Models\PayrollPeriod;
use App\Support\DtrPeriod;
use App\Support\ManilaTime;
use App\Support\PayrollSettings;
use Illuminate\Support\Facades\DB;

class PayrollAttendanceAggregator
{
    /**
     * @return array{employees: int, warnings: int}
     */
    public function aggregatePeriod(PayrollPeriod $period): array
    {
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $employeeCount = 0;
        $warningCount = 0;

        Employee::query()
            ->active()
            ->orderBy('id')
            ->chunkById(50, function ($employees) use ($period, $start, $end, &$employeeCount, &$warningCount) {
                foreach ($employees as $employee) {
                    $summary = $this->aggregateEmployee($employee, $period, $start, $end);
                    $employeeCount++;
                    $warningCount += count($summary->warnings ?? []);
                }
            });

        return ['employees' => $employeeCount, 'warnings' => $warningCount];
    }

    public function aggregateEmployee(Employee $employee, PayrollPeriod $period, ?string $start = null, ?string $end = null): PayrollAttendanceSummary
    {
        $start ??= $period->start_date->toDateString();
        $end ??= $period->end_date->toDateString();
        $schedule = $employee->schedule();
        $warnings = [];

        if (! $employee->designation_id) {
            $warnings[] = 'Missing designation';
        }

        if (! app(EmployeeSalaryService::class)->effectiveForDate($employee, $end)) {
            $warnings[] = 'No salary configuration effective for period end';
        }

        $dates = $this->datesInRange($start, $end);
        $scheduledDays = 0;
        foreach ($dates as $date) {
            if ($schedule->isWorkDay((int) ManilaTime::parse($date)->isoWeekday())) {
                $scheduledDays++;
            }
        }

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->betweenDates($start, $end)
            ->get()
            ->keyBy(fn ($row) => $row->attendance_date->toDateString());

        $leaveDays = $this->leaveDaysInPeriod($employee->id, $start, $end);

        $workedDays = 0;
        $absentDays = 0;
        $regularHours = 0.0;
        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $recordedOtHours = 0.0;
        $holidayHours = 0.0;
        $restDayHours = 0.0;
        $travelOrderDays = 0.0;
        $paidLeaveDays = $leaveDays['paid'];
        $unpaidLeaveDays = $leaveDays['unpaid'];
        $halfDayFraction = (float) config('payroll.attendance.half_day_absent_fraction', 0.5);

        foreach ($dates as $date) {
            if (! $schedule->isWorkDay((int) ManilaTime::parse($date)->isoWeekday())) {
                continue;
            }

            /** @var Attendance|null $row */
            $row = $attendance->get($date);
            $status = $row?->status;

            if ($status === AttendanceStatus::TravelOrder) {
                $travelOrderDays += 1;
                if (config('payroll.travel_order_counts_as_paid_day', true)) {
                    $workedDays++;
                }

                continue;
            }

            if ($status === AttendanceStatus::OnLeave) {
                continue;
            }

            if ($status === AttendanceStatus::Holiday) {
                $holidayHours += ($row->total_minutes ?? 0) / 60;

                continue;
            }

            if ($status === AttendanceStatus::RestDay) {
                $restDayHours += ($row->total_minutes ?? 0) / 60;

                continue;
            }

            if (! $row || $status === AttendanceStatus::Absent) {
                $absentDays += 1;

                continue;
            }

            if ($status === AttendanceStatus::HalfDay) {
                $absentDays += $halfDayFraction;
                $workedDays += $halfDayFraction;
                $regularHours += ($row->total_minutes ?? 0) / 60;
                $lateMinutes += (int) $row->late_minutes;
                $undertimeMinutes += (int) $row->undertime_minutes;
                $recordedOtHours += ($row->overtime_minutes ?? 0) / 60;

                continue;
            }

            if (in_array($status, [
                AttendanceStatus::Present,
                AttendanceStatus::Late,
                AttendanceStatus::Undertime,
                AttendanceStatus::Overtime,
                AttendanceStatus::Incomplete,
            ], true)) {
                if ($status !== AttendanceStatus::Incomplete || $row->hasTimeIn()) {
                    $workedDays++;
                }
                $regularHours += ($row->total_minutes ?? 0) / 60;
                $lateMinutes += (int) $row->late_minutes;
                $undertimeMinutes += (int) $row->undertime_minutes;
                $recordedOtHours += ($row->overtime_minutes ?? 0) / 60;
            }
        }

        $paidDays = max(0, $scheduledDays - $absentDays - $unpaidLeaveDays);
        if (config('payroll.travel_order_counts_as_paid_day', true)) {
            $paidDays = max($paidDays, $workedDays + $paidLeaveDays);
        }

        if (PayrollSettings::overtimeRequiresApproval()) {
            $approvedOtHours = $this->approvedOtHours($employee->id, $start, $end);
            if ($recordedOtHours > 0 && $approvedOtHours <= 0) {
                $warnings[] = 'Recorded OT pending approval';
            }
        } else {
            $approvedOtHours = $recordedOtHours;
        }

        $payload = [
            'scheduled_days' => $scheduledDays,
            'worked_days' => $workedDays,
            'paid_days' => round($paidDays, 2),
            'absent_days' => round($absentDays, 2),
            'regular_hours' => round($regularHours, 2),
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'approved_ot_hours' => round($approvedOtHours, 2),
            'recorded_ot_hours' => round($recordedOtHours, 2),
            'holiday_hours' => round($holidayHours, 2),
            'rest_day_hours' => round($restDayHours, 2),
            'leave_days' => round($paidLeaveDays + $unpaidLeaveDays, 2),
            'paid_leave_days' => round($paidLeaveDays, 2),
            'unpaid_leave_days' => round($unpaidLeaveDays, 2),
            'travel_order_days' => round($travelOrderDays, 2),
            'warnings' => $warnings === [] ? null : $warnings,
            'computed_at' => now(),
        ];

        return DB::transaction(function () use ($period, $employee, $payload) {
            return PayrollAttendanceSummary::query()->updateOrCreate(
                [
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employee->id,
                ],
                $payload
            );
        });
    }

    /**
     * @return array{paid: float, unpaid: float}
     */
    private function leaveDaysInPeriod(int $employeeId, string $start, string $end): array
    {
        $paid = 0.0;
        $unpaid = 0.0;

        $applications = LeaveApplication::query()
            ->where('employee_id', $employeeId)
            ->where('status', LeaveStatus::Approved->value)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get(['start_date', 'end_date', 'requested_days', 'payment_type']);

        foreach ($applications as $application) {
            $overlapStart = max($start, $application->start_date->toDateString());
            $overlapEnd = min($end, $application->end_date->toDateString());
            $days = $this->countWeekdays($overlapStart, $overlapEnd);
            $payment = $application->payment_type ?? LeavePaymentType::WithPay;

            if ($payment === LeavePaymentType::WithoutPay) {
                $unpaid += $days;
            } else {
                $paid += $days;
            }
        }

        return ['paid' => $paid, 'unpaid' => $unpaid];
    }

    /** @return list<string> */
    private function datesInRange(string $start, string $end): array
    {
        $dates = [];
        $cursor = ManilaTime::parse($start)->startOfDay();
        $last = ManilaTime::parse($end)->startOfDay();

        while ($cursor->lte($last)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->copy()->addDay();
        }

        return $dates;
    }

    private function approvedOtHours(int $employeeId, string $start, string $end): float
    {
        $minutes = OvertimeRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', OvertimeRequestStatus::Approved)
            ->whereDate('attendance_date', '>=', $start)
            ->whereDate('attendance_date', '<=', $end)
            ->sum('approved_minutes');

        return round($minutes / 60, 2);
    }

    private function countWeekdays(string $start, string $end): float
    {
        $count = 0;
        foreach ($this->datesInRange($start, $end) as $date) {
            $dow = (int) ManilaTime::parse($date)->isoWeekday();
            if ($dow >= 1 && $dow <= 5) {
                $count++;
            }
        }

        return (float) $count;
    }

    public static function suggestPeriodFromDtr(?DtrPeriod $dtr = null): array
    {
        $dtr ??= DtrPeriod::current();

        return [
            'period_name' => 'Payroll '.$dtr->cutoffLabel,
            'start_date' => $dtr->start,
            'end_date' => $dtr->end,
            'period_key' => $dtr->key,
        ];
    }
}
