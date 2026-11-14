<?php

namespace App\Services;

use App\Enums\AttendancePunchType;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Leave;
use App\Models\OfficialBusinessRequest;
use App\Models\TravelOrder;
use App\Models\WorkSchedule;
use App\Support\ManilaTime;
use Carbon\Carbon;

class AttendanceCalculator
{
    public function __construct(private ?HolidayResolver $holidays = null) {}

    private function holidays(): HolidayResolver
    {
        return $this->holidays ??= app(HolidayResolver::class);
    }

    /**
     * @param  array{am_time_in: ?Carbon, am_time_out: ?Carbon, pm_time_in: ?Carbon, pm_time_out: ?Carbon, overtime_in: ?Carbon}  $punches
     */
    public function calculateFromPunches(
        string $date,
        array $punches,
        WorkSchedule $schedule,
        ?int $employeeId = null,
        ?AttendanceStatus $forcedStatus = null
    ): array {
        if ($forcedStatus) {
            return $this->emptyResult($forcedStatus);
        }

        if ($employeeId && Leave::approvedOn($employeeId, $date)) {
            return $this->emptyResult(AttendanceStatus::OnLeave);
        }

        if ($employeeId && TravelOrder::approvedOn($employeeId, $date)) {
            return $this->emptyResult(AttendanceStatus::TravelOrder);
        }

        if ($employeeId && OfficialBusinessRequest::approvedOn($employeeId, $date)) {
            return $this->emptyResult(AttendanceStatus::OfficialBusiness);
        }

        $holiday = $this->holidays()->forDate($date, $employeeId);
        $day = ManilaTime::parse($date);
        $isWorkDay = $schedule->isWorkDay((int) $day->isoWeekday());

        $amIn = $punches['am_time_in'] ?? null;
        $amOut = $punches['am_time_out'] ?? null;
        $pmIn = $punches['pm_time_in'] ?? null;
        $pmOut = $punches['pm_time_out'] ?? null;
        $overtimeIn = $punches['overtime_in'] ?? null;

        if (! $amIn && ! $amOut && ! $pmIn && ! $pmOut && ! $overtimeIn) {
            if ($holiday) {
                return $this->emptyResult(AttendanceStatus::Holiday);
            }

            if (! $isWorkDay) {
                return $this->emptyResult(AttendanceStatus::RestDay);
            }

            return $this->emptyResult(AttendanceStatus::Absent);
        }

        $start = ManilaTime::combineDateAndTime($date, (string) $schedule->start_time);
        $end = ManilaTime::combineDateAndTime($date, (string) $schedule->end_time);
        $allowedIn = $start->copy()->addMinutes((int) $schedule->grace_period_minutes);

        $lateMinutes = 0;
        if ($amIn && $amIn->gt($allowedIn)) {
            $lateMinutes = (int) floor(($amIn->getTimestamp() - $allowedIn->getTimestamp()) / 60);
        }

        $amMinutes = ($amIn && $amOut && $amOut->gt($amIn))
            ? (int) floor(($amOut->getTimestamp() - $amIn->getTimestamp()) / 60)
            : 0;
        $pmMinutes = ($pmIn && $pmOut && $pmOut->gt($pmIn))
            ? (int) floor(($pmOut->getTimestamp() - $pmIn->getTimestamp()) / 60)
            : 0;
        $totalMinutes = max(0, $amMinutes + $pmMinutes);

        if (! $pmOut && ($amIn || $pmIn)) {
            return [
                'total_minutes' => $totalMinutes,
                'late_minutes' => max(0, $lateMinutes),
                'undertime_minutes' => 0,
                'overtime_minutes' => 0,
                'status' => AttendanceStatus::Incomplete,
            ];
        }

        $undertimeMinutes = 0;
        if ($pmOut && $pmOut->lt($end)) {
            $undertimeMinutes = (int) floor(($end->getTimestamp() - $pmOut->getTimestamp()) / 60);
        }

        $overtimeMinutes = 0;
        if ($overtimeIn && $pmOut && $overtimeIn->gt($pmOut)) {
            $overtimeMinutes = (int) floor(($overtimeIn->getTimestamp() - $pmOut->getTimestamp()) / 60);
        } elseif ($pmOut && $pmOut->gt($end)) {
            $overtimeMinutes = (int) floor(($pmOut->getTimestamp() - $end->getTimestamp()) / 60);
        }

        if ($holiday || ! $isWorkDay) {
            $overtimeMinutes = max($overtimeMinutes, $totalMinutes);
            $undertimeMinutes = 0;
        }

        $required = $schedule->effectiveRequiredMinutes();

        $regularComplete = $this->hasAllRegularPunches($amIn, $amOut, $pmIn, $pmOut);

        $totalMinutes = Attendance::creditDisplayMinutes(
            $totalMinutes,
            max(0, $lateMinutes),
            max(0, $undertimeMinutes),
            $required,
            $regularComplete,
        );

        $status = $this->resolveStatus(
            $lateMinutes,
            $undertimeMinutes,
            $overtimeMinutes,
            $totalMinutes,
            $required
        );

        return [
            'total_minutes' => $totalMinutes,
            'late_minutes' => max(0, $lateMinutes),
            'undertime_minutes' => max(0, $undertimeMinutes),
            'overtime_minutes' => max(0, $overtimeMinutes),
            'status' => $status,
        ];
    }

    /** @deprecated Use calculateFromPunches() — kept for legacy callers during transition. */
    public function calculate(
        string $date,
        ?Carbon $timeIn,
        ?Carbon $timeOut,
        WorkSchedule $schedule,
        ?int $employeeId = null,
        ?AttendanceStatus $forcedStatus = null
    ): array {
        if (! $timeIn && ! $timeOut) {
            return $this->calculateFromPunches($date, [
                'am_time_in' => null,
                'am_time_out' => null,
                'pm_time_in' => null,
                'pm_time_out' => null,
                'overtime_in' => null,
            ], $schedule, $employeeId, $forcedStatus);
        }

        $presenter = app(DtrDayPresenter::class);
        [$amIn, $amOut, $pmIn, $pmOut] = $presenter->splitPunches($date, $timeIn, $timeOut, $schedule);

        return $this->calculateFromPunches($date, [
            'am_time_in' => $amIn,
            'am_time_out' => $amOut,
            'pm_time_in' => $pmIn,
            'pm_time_out' => $pmOut,
            'overtime_in' => null,
        ], $schedule, $employeeId, $forcedStatus);
    }

    private function resolveStatus(
        int $lateMinutes,
        int $undertimeMinutes,
        int $overtimeMinutes,
        int $netMinutes,
        int $required
    ): AttendanceStatus {
        if ($required > 0 && $netMinutes > 0 && $netMinutes < (int) floor($required / 2)) {
            return AttendanceStatus::HalfDay;
        }

        if ($lateMinutes > 0) {
            return AttendanceStatus::Late;
        }

        if ($undertimeMinutes > 0) {
            return AttendanceStatus::Undertime;
        }

        if ($overtimeMinutes > 0) {
            return AttendanceStatus::Overtime;
        }

        return AttendanceStatus::Present;
    }

    private function hasAllRegularPunches(?Carbon $amIn, ?Carbon $amOut, ?Carbon $pmIn, ?Carbon $pmOut): bool
    {
        foreach (AttendancePunchType::regularSequence() as $type) {
            $present = match ($type) {
                AttendancePunchType::AmTimeIn => $amIn,
                AttendancePunchType::AmTimeOut => $amOut,
                AttendancePunchType::PmTimeIn => $pmIn,
                AttendancePunchType::PmTimeOut => $pmOut,
                default => null,
            };

            if (! $present) {
                return false;
            }
        }

        return true;
    }

    private function emptyResult(AttendanceStatus $status): array
    {
        return [
            'total_minutes' => 0,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => $status,
        ];
    }
}
