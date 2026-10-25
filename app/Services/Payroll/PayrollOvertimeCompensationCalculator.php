<?php

namespace App\Services\Payroll;

use App\Enums\OvertimeRequestStatus;
use App\Enums\PayrollWorkdayClassification;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\PayrollOvertimeCompensationRate;
use App\Models\PayrollPeriod;
use App\Support\ManilaTime;
use App\Support\Money;
use App\Support\PayrollSettings;
use Carbon\Carbon;

class PayrollOvertimeCompensationCalculator
{
    public function __construct(
        private readonly OvertimeWorkdayClassifier $classifier,
        private readonly PayrollOvertimeCompensationRateService $rates,
    ) {}

    /**
     * OT pay uses configured full multipliers (not premium-only deltas).
     * Night-shift OT rates already embed NSD; no separate NSD is applied to the same hours.
     *
     * @return array{
     *     overtime_pay: float,
     *     breakdown: list<array<string, mixed>>,
     *     errors: list<string>,
     *     warnings: list<string>,
     *     effective_multiplier: ?float
     * }
     */
    public function calculate(Employee $employee, PayrollPeriod $period, float $hourlyRate): array
    {
        if ($hourlyRate <= 0) {
            return [
                'overtime_pay' => 0.0,
                'breakdown' => [],
                'errors' => [],
                'warnings' => [],
                'effective_multiplier' => null,
            ];
        }

        if (! PayrollSettings::useOvertimeCompensationMatrix()) {
            return $this->legacyFlatCalculation($employee, $period, $hourlyRate);
        }

        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $schedule = $employee->schedule();

        $records = Attendance::query()
            ->where('employee_id', $employee->id)
            ->betweenDates($start, $end)
            ->get();

        $breakdown = [];
        $errors = [];
        $warnings = [];
        $totalPay = 0.0;

        foreach ($records as $record) {
            $date = $record->attendance_date->toDateString();
            $recordedMinutes = max(0, (int) ($record->overtime_minutes ?? 0));
            if ($recordedMinutes <= 0) {
                continue;
            }

            $payableMinutes = $this->payableOtMinutes($employee->id, $date, $recordedMinutes);
            if ($payableMinutes <= 0) {
                continue;
            }

            $segments = $this->buildOtSegments($record, $schedule, $date, $payableMinutes);
            $dayInfo = $this->classifier->classify($date, $employee, $record, $schedule);

            foreach ($segments as $segment) {
                $segmentDate = $segment['date'];
                $hours = $segment['hours'];
                if ($hours <= 0) {
                    continue;
                }

                $info = $segmentDate === $date
                    ? $dayInfo
                    : $this->classifier->classify($segmentDate, $employee, $record, $schedule);

                /** @var PayrollOvertimeCompensationRate|null $rate */
                $rate = $this->rates->resolveRate(
                    $info['classification'],
                    $info['is_rest_day'],
                    $segment['is_night_shift'],
                    $segmentDate,
                );

                if (! $rate) {
                    $errors[] = sprintf(
                        'Missing OT compensation rate for %s (%s, %s%s) effective %s.',
                        $segmentDate,
                        $info['classification']->label(),
                        $info['is_rest_day'] ? 'rest day' : 'workday',
                        $segment['is_night_shift'] ? ', night shift' : '',
                        $segmentDate,
                    );

                    continue;
                }

                $amount = Money::toFloat(Money::round($hours * $hourlyRate * (float) $rate->multiplier));
                $totalPay += $amount;

                $breakdown[] = [
                    'employee_id' => $employee->id,
                    'payroll_period_id' => $period->id,
                    'work_date' => $segmentDate,
                    'day_classification' => $info['classification']->value,
                    'day_classification_label' => $info['classification_label'],
                    'rest_day' => $info['is_rest_day'],
                    'rest_day_label' => $info['rest_day_label'],
                    'qualified_ot_hours' => round($hours, 4),
                    'night_shift_hours' => $segment['is_night_shift'] ? round($hours, 4) : 0,
                    'applied_multiplier' => (float) $rate->multiplier,
                    'applied_percentage' => $rate->percentage(),
                    'hourly_rate' => $hourlyRate,
                    'computed_ot_amount' => $amount,
                    'rate_id' => $rate->id,
                    'rate_label' => $rate->label,
                    'condition_code' => $rate->condition_code,
                    'effective_from' => $rate->effective_from?->toDateString(),
                ];
            }
        }

        return [
            'overtime_pay' => Money::toFloat(Money::round($totalPay)),
            'breakdown' => $breakdown,
            'errors' => array_values(array_unique($errors)),
            'warnings' => $warnings,
            'effective_multiplier' => $this->weightedMultiplier($breakdown),
        ];
    }

    /**
     * @return array{overtime_pay: float, breakdown: list<array<string, mixed>>, errors: list<string>, warnings: list<string>, effective_multiplier: ?float}
     */
    private function legacyFlatCalculation(Employee $employee, PayrollPeriod $period, float $hourlyRate): array
    {
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $multiplier = PayrollSettings::overtimeMultiplier();
        $hours = 0.0;

        $records = Attendance::query()
            ->where('employee_id', $employee->id)
            ->betweenDates($start, $end)
            ->get();

        foreach ($records as $record) {
            $date = $record->attendance_date->toDateString();
            $recordedMinutes = max(0, (int) ($record->overtime_minutes ?? 0));
            $hours += $this->payableOtMinutes($employee->id, $date, $recordedMinutes) / 60;
        }

        $hours = round($hours, 4);
        $pay = Money::toFloat(Money::round($hours * $hourlyRate * $multiplier));

        return [
            'overtime_pay' => $pay,
            'breakdown' => $hours > 0 ? [[
                'work_date' => null,
                'qualified_ot_hours' => $hours,
                'applied_multiplier' => $multiplier,
                'applied_percentage' => round($multiplier * 100, 2),
                'hourly_rate' => $hourlyRate,
                'computed_ot_amount' => $pay,
                'rate_label' => 'Legacy flat OT multiplier',
            ]] : [],
            'errors' => [],
            'warnings' => [],
            'effective_multiplier' => $multiplier,
        ];
    }

    private function payableOtMinutes(int $employeeId, string $date, int $recordedMinutes): int
    {
        if ($recordedMinutes <= 0) {
            return 0;
        }

        if (! PayrollSettings::overtimeRequiresApproval()) {
            return $recordedMinutes;
        }

        $approved = (int) OvertimeRequest::query()
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date)
            ->where('status', OvertimeRequestStatus::Approved)
            ->sum('approved_minutes');

        return min($recordedMinutes, max(0, $approved));
    }

    /**
     * @return list<array{date: string, hours: float, is_night_shift: bool}>
     */
    private function buildOtSegments(Attendance $record, $schedule, string $date, int $payableMinutes): array
    {
        $interval = $this->resolveOtInterval($record, $schedule, $date);
        if (! $interval) {
            return [[
                'date' => $date,
                'hours' => round($payableMinutes / 60, 4),
                'is_night_shift' => false,
            ]];
        }

        [$start, $end] = $interval;
        $nightStart = PayrollSettings::nightShiftStart();
        $nightEnd = PayrollSettings::nightShiftEnd();

        $rawSegments = $this->splitIntervalByMidnight($start, $end);
        $segments = [];

        foreach ($rawSegments as $piece) {
            foreach ($this->splitNightDay($piece['start'], $piece['end'], $nightStart, $nightEnd) as $sub) {
                $minutes = max(0, (int) floor(($sub['end']->getTimestamp() - $sub['start']->getTimestamp()) / 60));
                if ($minutes <= 0) {
                    continue;
                }
                $segments[] = [
                    'date' => $sub['start']->toDateString(),
                    'minutes' => $minutes,
                    'is_night_shift' => $sub['is_night'],
                ];
            }
        }

        if ($segments === []) {
            return [[
                'date' => $date,
                'hours' => round($payableMinutes / 60, 4),
                'is_night_shift' => false,
            ]];
        }

        $totalSegmentMinutes = array_sum(array_column($segments, 'minutes'));
        $scale = $totalSegmentMinutes > 0 ? $payableMinutes / $totalSegmentMinutes : 1;

        return array_map(function (array $row) use ($scale) {
            $minutes = (int) round($row['minutes'] * $scale);

            return [
                'date' => $row['date'],
                'hours' => round($minutes / 60, 4),
                'is_night_shift' => $row['is_night_shift'],
            ];
        }, array_filter($segments, fn ($row) => $row['minutes'] > 0));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function resolveOtInterval(Attendance $record, $schedule, string $date): ?array
    {
        $end = ManilaTime::combineDateAndTime($date, (string) $schedule->end_time);
        $pmOut = $record->pm_time_out ? ManilaTime::parse($record->pm_time_out) : null;
        $overtimeIn = $record->overtime_in ? ManilaTime::parse($record->overtime_in) : null;

        if ($overtimeIn && $pmOut && $overtimeIn->gt($pmOut)) {
            return [$pmOut->copy(), $overtimeIn->copy()];
        }

        if ($pmOut && $pmOut->gt($end)) {
            return [$end->copy(), $pmOut->copy()];
        }

        $minutes = max(0, (int) ($record->overtime_minutes ?? 0));
        if ($minutes > 0 && $pmOut) {
            return [$pmOut->copy(), $pmOut->copy()->addMinutes($minutes)];
        }

        return null;
    }

    /**
     * @return list<array{start: Carbon, end: Carbon}>
     */
    private function splitIntervalByMidnight(Carbon $start, Carbon $end): array
    {
        $pieces = [];
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $dayEnd = $cursor->copy()->startOfDay()->addDay();
            $pieceEnd = $end->lt($dayEnd) ? $end->copy() : $dayEnd;
            $pieces[] = ['start' => $cursor->copy(), 'end' => $pieceEnd];
            $cursor = $pieceEnd->copy();
        }

        return $pieces;
    }

    /**
     * Night window may cross midnight (e.g. 22:00–06:00).
     *
     * @return list<array{start: Carbon, end: Carbon, is_night: bool}>
     */
    private function splitNightDay(Carbon $start, Carbon $end, string $nightStart, string $nightEnd): array
    {
        $segments = [];
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $isNight = $this->isNightMoment($cursor, $nightStart, $nightEnd);
            $nextBoundary = $this->nextBoundary($cursor, $nightStart, $nightEnd, $isNight);
            $pieceEnd = $end->lt($nextBoundary) ? $end->copy() : $nextBoundary;
            $segments[] = ['start' => $cursor->copy(), 'end' => $pieceEnd, 'is_night' => $isNight];
            $cursor = $pieceEnd->copy();
        }

        return $segments;
    }

    private function isNightMoment(Carbon $moment, string $nightStart, string $nightEnd): bool
    {
        $time = $moment->format('H:i:s');
        if ($nightStart <= $nightEnd) {
            return $time >= $nightStart && $time < $nightEnd;
        }

        return $time >= $nightStart || $time < $nightEnd;
    }

    private function nextBoundary(Carbon $moment, string $nightStart, string $nightEnd, bool $currentlyNight): Carbon
    {
        $date = $moment->toDateString();
        if ($currentlyNight) {
            if ($nightStart <= $nightEnd) {
                return ManilaTime::combineDateAndTime($date, $nightEnd);
            }

            if ($moment->format('H:i:s') >= $nightStart) {
                return ManilaTime::combineDateAndTime(
                    ManilaTime::parse($date)->addDay()->toDateString(),
                    $nightEnd
                );
            }

            return ManilaTime::combineDateAndTime($date, $nightEnd);
        }

        if ($nightStart <= $nightEnd) {
            if ($moment->format('H:i:s') < $nightStart) {
                return ManilaTime::combineDateAndTime($date, $nightStart);
            }

            return ManilaTime::combineDateAndTime(
                ManilaTime::parse($date)->addDay()->toDateString(),
                $nightStart
            );
        }

        if ($moment->format('H:i:s') < $nightEnd) {
            return ManilaTime::combineDateAndTime($date, $nightEnd);
        }

        if ($moment->format('H:i:s') < $nightStart) {
            return ManilaTime::combineDateAndTime($date, $nightStart);
        }

        return ManilaTime::combineDateAndTime(
            ManilaTime::parse($date)->addDay()->toDateString(),
            $nightEnd
        );
    }

    /**
     * @param  list<array<string, mixed>>  $breakdown
     */
    private function weightedMultiplier(array $breakdown): ?float
    {
        $hours = 0.0;
        $weighted = 0.0;
        foreach ($breakdown as $row) {
            $h = (float) ($row['qualified_ot_hours'] ?? 0);
            $m = (float) ($row['applied_multiplier'] ?? 0);
            $hours += $h;
            $weighted += $h * $m;
        }

        if ($hours <= 0) {
            return null;
        }

        return round($weighted / $hours, 4);
    }
}
