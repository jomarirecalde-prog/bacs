<?php

namespace App\Services;

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AttendancePunchType;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Support\ManilaTime;
use Carbon\Carbon;

class AttendanceCorrectionDayPreview
{
    public function __construct(private readonly DtrDayPresenter $presenter) {}

    /**
     * @return array<string, mixed>
     */
    public function forEmployee(Employee $employee, string $date): array
    {
        $schedule = $employee->schedule();
        $day = ManilaTime::parse($date);
        $isWorkDay = $schedule->isWorkDay($day->dayOfWeekIso);
        $isFuture = $day->gt(ManilaTime::today());

        $record = Attendance::query()
            ->where('employee_id', $employee->id)
            ->onDate($date)
            ->first();

        $punchTimes = $this->resolvePunchTimes($date, $record, $schedule);
        $pendingTypes = $this->pendingPunchTypes($employee->id, $date);

        $status = $record?->status instanceof AttendanceStatus
            ? $record->status
            : ($record ? AttendanceStatus::tryFrom((string) $record->status) : null);

        if (! $status && $record === null && $isWorkDay && ! $isFuture) {
            $status = AttendanceStatus::Absent;
        }

        $lateMinutes = (int) ($record?->late_minutes ?? 0);
        $undertimeMinutes = (int) ($record?->undertime_minutes ?? 0);
        $incomplete = $record ? $this->presenterIncomplete($record, $isFuture) : false;

        $punches = [];
        foreach (AttendancePunchType::regularSequence() as $type) {
            $punches[] = $this->punchEntry(
                $type,
                $punchTimes[$type->value] ?? null,
                $lateMinutes,
                $undertimeMinutes,
                in_array($type->value, $pendingTypes, true),
                $status,
            );
        }

        $missingCount = collect($punches)->filter(fn (array $p) => $p['missing'])->count();

        return [
            'date' => $date,
            'date_label' => $day->format('F j, Y'),
            'day_name' => $day->format('l'),
            'is_work_day' => $isWorkDay,
            'is_future' => $isFuture,
            'schedule' => [
                'name' => $schedule->name,
                'start' => $this->scheduleTimeLabel($schedule->start_time),
                'end' => $this->scheduleTimeLabel($schedule->end_time),
                'break_start' => $this->scheduleTimeLabel($schedule->break_start ?? '12:00:00'),
                'break_end' => $this->scheduleTimeLabel($schedule->break_end ?? '13:00:00'),
            ],
            'day_status' => $status?->label(),
            'day_status_value' => $status?->value,
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'incomplete' => $incomplete,
            'missing_punch_count' => $missingCount,
            'highlights' => $this->highlights(
                $isWorkDay,
                $isFuture,
                $status,
                $lateMinutes,
                $undertimeMinutes,
                $incomplete,
                $missingCount,
            ),
            'punches' => $punches,
        ];
    }

    /**
     * @return array<string, ?Carbon>
     */
    private function resolvePunchTimes(string $date, ?Attendance $record, WorkSchedule $schedule): array
    {
        if (! $record) {
            return [
                'am_time_in' => null,
                'am_time_out' => null,
                'pm_time_in' => null,
                'pm_time_out' => null,
            ];
        }

        if ($record->am_time_in || $record->am_time_out || $record->pm_time_in || $record->pm_time_out) {
            return [
                'am_time_in' => $record->am_time_in,
                'am_time_out' => $record->am_time_out,
                'pm_time_in' => $record->pm_time_in,
                'pm_time_out' => $record->pm_time_out,
            ];
        }

        [$amIn, $amOut, $pmIn, $pmOut] = $this->presenter->splitPunches(
            $date,
            $record->time_in,
            $record->time_out,
            $schedule,
        );

        return [
            'am_time_in' => $amIn,
            'am_time_out' => $amOut,
            'pm_time_in' => $pmIn,
            'pm_time_out' => $pmOut,
        ];
    }

    /**
     * @return list<string>
     */
    private function pendingPunchTypes(int $employeeId, string $date): array
    {
        return AttendanceCorrectionRequest::query()
            ->where('employee_id', $employeeId)
            ->forDate($date)
            ->whereIn('status', [
                AttendanceCorrectionStatus::Pending->value,
                AttendanceCorrectionStatus::PendingEndorsement->value,
                AttendanceCorrectionStatus::PendingFinalApproval->value,
            ])
            ->pluck('punch_type')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function punchEntry(
        AttendancePunchType $type,
        ?Carbon $at,
        int $lateMinutes,
        int $undertimeMinutes,
        bool $pending,
        ?AttendanceStatus $dayStatus,
    ): array {
        $missing = $at === null;
        $tags = [];

        if ($missing) {
            $tags[] = ['key' => 'missing', 'label' => 'Missing', 'tone' => 'warn'];
        } else {
            $tags[] = ['key' => 'recorded', 'label' => 'Recorded', 'tone' => 'ok'];
        }

        if ($type === AttendancePunchType::AmTimeIn && $lateMinutes > 0 && ! $missing) {
            $tags[] = ['key' => 'late', 'label' => 'Late '.$lateMinutes.' min', 'tone' => 'warn'];
        }

        if ($type === AttendancePunchType::PmTimeOut && $undertimeMinutes > 0 && ! $missing) {
            $tags[] = ['key' => 'undertime', 'label' => 'Undertime '.$undertimeMinutes.' min', 'tone' => 'warn'];
        }

        if ($pending) {
            $tags[] = ['key' => 'pending', 'label' => 'Correction pending', 'tone' => 'info'];
        }

        if ($dayStatus === AttendanceStatus::OnLeave && $missing) {
            $tags[] = ['key' => 'on_leave', 'label' => 'On leave', 'tone' => 'info'];
        }

        if ($dayStatus === AttendanceStatus::TravelOrder && $missing) {
            $tags[] = ['key' => 'travel', 'label' => 'Travel order', 'tone' => 'info'];
        }

        return [
            'type' => $type->value,
            'label' => $type->label(),
            'time' => $at ? ManilaTime::formatTime($at) : null,
            'time_24' => $at ? $at->format('H:i') : null,
            'missing' => $missing,
            'pending' => $pending,
            'tags' => $tags,
        ];
    }

    /**
     * @return list<string>
     */
    private function highlights(
        bool $isWorkDay,
        bool $isFuture,
        ?AttendanceStatus $status,
        int $lateMinutes,
        int $undertimeMinutes,
        bool $incomplete,
        int $missingCount,
    ): array {
        $lines = [];

        if ($isFuture) {
            $lines[] = 'This date is in the future. Choose a date on or before today.';

            return $lines;
        }

        if (! $isWorkDay) {
            $lines[] = 'This date is not a regular work day on your schedule.';
        }

        if ($status === AttendanceStatus::OnLeave) {
            $lines[] = 'You are marked on leave for this date.';
        } elseif ($status === AttendanceStatus::TravelOrder) {
            $lines[] = 'You have an approved travel order for this date.';
        } elseif ($status === AttendanceStatus::RestDay) {
            $lines[] = 'This is a rest day on your record.';
        } elseif ($status === AttendanceStatus::Holiday) {
            $lines[] = 'This date is marked as a holiday.';
        }

        if ($missingCount > 0) {
            $lines[] = $missingCount === 4
                ? 'No attendance punches were recorded for this day.'
                : $missingCount.' required punch'.($missingCount === 1 ? ' is' : 'es are').' missing.';
        }

        if ($lateMinutes > 0) {
            $lines[] = 'Late arrival: '.$lateMinutes.' minute(s).';
        }

        if ($undertimeMinutes > 0) {
            $lines[] = 'Undertime: '.$undertimeMinutes.' minute(s).';
        }

        if ($incomplete) {
            $lines[] = 'This day is incomplete — expected PM Time Out (or earlier punches) may still be missing.';
        }

        if ($lines === [] && $isWorkDay) {
            $lines[] = 'All four punches are recorded for this day.';
        }

        return $lines;
    }

    private function presenterIncomplete(Attendance $record, bool $isFuture): bool
    {
        if ($isFuture) {
            return false;
        }

        if ($record->am_time_in || $record->am_time_out || $record->pm_time_in || $record->pm_time_out || $record->overtime_in) {
            return (bool) ($record->am_time_in || $record->pm_time_in) && ! $record->pm_time_out;
        }

        return (bool) $record->time_in && ! $record->time_out;
    }

    private function scheduleTimeLabel(mixed $time): string
    {
        if ($time instanceof Carbon) {
            return $time->format('g:i A');
        }

        $text = trim((string) $time);

        return Carbon::parse('1970-01-01 '.($text !== '' ? $text : '00:00:00'))->format('g:i A');
    }
}
