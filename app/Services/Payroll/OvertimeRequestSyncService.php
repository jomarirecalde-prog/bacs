<?php

namespace App\Services\Payroll;

use App\Enums\OfficialTimeStatus;
use App\Enums\OvertimeRequestStatus;
use App\Models\Attendance;
use App\Models\OfficialTimeRequest;
use App\Models\OvertimeAttachmentReport;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Enums\OvertimeAttachmentReportStatus;
use App\Support\DeclaredOvertimeDuration;
use Illuminate\Support\Carbon;

class OvertimeRequestSyncService
{
    public function __construct(private readonly OvertimeCentralWorkflowService $workflow) {}

    public function syncPeriod(PayrollPeriod $period): int
    {
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $this->normalizeLegacyAttachmentStatusesForPeriod($start, $end);

        $count = $this->syncAttendanceOvertimeBetween($start, $end);
        $count += $this->syncOfficialTimeBetween($start, $end);

        return $count;
    }

    private function normalizeLegacyAttachmentStatusesForPeriod(string $start, string $end): void
    {
        OvertimeAttachmentReport::query()
            ->whereIn('status', [
                OvertimeAttachmentReportStatus::UnderReview,
                OvertimeAttachmentReportStatus::Verified,
            ])
            ->whereNull('approved_at')
            ->whereHas('overtimeRequest', function ($q) use ($start, $end) {
                $q->whereBetween('attendance_date', [$start, $end]);
            })
            ->update([
                'status' => OvertimeAttachmentReportStatus::Submitted,
                'current_stage' => null,
            ]);
    }

    public function syncForEmployee(\App\Models\Employee $employee, ?Carbon $from = null, ?Carbon $to = null): int
    {
        $from ??= now()->subMonths(3)->startOfMonth();
        $to ??= now()->addMonth()->endOfMonth();

        $this->normalizeLegacyAttachmentStatusesForEmployee($employee->id);

        $count = $this->syncAttendanceOvertimeBetween(
            $from->toDateString(),
            $to->toDateString(),
            $employee->id,
        );
        $count += $this->syncOfficialTimeBetween(
            $from->toDateString(),
            $to->toDateString(),
            $employee->id,
        );

        return $count;
    }

    private function normalizeLegacyAttachmentStatusesForEmployee(int $employeeId): void
    {
        OvertimeAttachmentReport::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [
                OvertimeAttachmentReportStatus::UnderReview,
                OvertimeAttachmentReportStatus::Verified,
            ])
            ->whereNull('approved_at')
            ->update([
                'status' => OvertimeAttachmentReportStatus::Submitted,
                'current_stage' => null,
            ]);
    }

    public function upsertFromOfficialTime(OfficialTimeRequest $officialTime): ?OvertimeRequest
    {
        if (in_array($officialTime->status, [OfficialTimeStatus::Draft, OfficialTimeStatus::Cancelled], true)) {
            return null;
        }

        $minutes = (int) $officialTime->duration_minutes;
        if ($minutes <= 0) {
            return null;
        }

        $date = $officialTime->date?->toDateString();
        if (! $date) {
            return null;
        }

        $attendance = Attendance::query()
            ->where('employee_id', $officialTime->employee_id)
            ->whereDate('attendance_date', $date)
            ->first();

        $request = OvertimeRequest::query()->firstOrNew([
            'employee_id' => $officialTime->employee_id,
            'attendance_date' => $date,
        ]);

        if ($attendance) {
            $request->attendance_id = $attendance->id;
        }

        $clockOvertime = (int) ($attendance?->overtime_minutes ?? 0);
        $this->setRecordedMinutes(
            $request,
            $this->resolveRecordedMinutes($officialTime->employee_id, $date, $clockOvertime),
        );

        $request->save();

        return $request->fresh(['employee']);
    }

    private function syncAttendanceOvertimeBetween(string $start, string $end, ?int $employeeId = null): int
    {
        $count = 0;

        Attendance::query()
            ->whereBetween('attendance_date', [$start, $end])
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->where('overtime_minutes', '>', 0)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    $request = OvertimeRequest::query()->firstOrNew([
                        'employee_id' => $row->employee_id,
                        'attendance_date' => $row->attendance_date,
                    ]);

                    $request->attendance_id = $row->id;
                    $date = $row->attendance_date->toDateString();
                    $this->setRecordedMinutes(
                        $request,
                        $this->resolveRecordedMinutes($row->employee_id, $date, (int) $row->overtime_minutes),
                    );

                    $request->save();
                    $this->workflow->bootstrapIfNeeded($request->fresh(['employee']));
                    $count++;
                }
            });

        return $count;
    }

    private function syncOfficialTimeBetween(string $start, string $end, ?int $employeeId = null): int
    {
        $count = 0;

        OfficialTimeRequest::query()
            ->whereBetween('date', [$start, $end])
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->whereNotIn('status', [OfficialTimeStatus::Draft, OfficialTimeStatus::Cancelled])
            ->orderBy('id')
            ->chunkById(50, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    if ($this->upsertFromOfficialTime($row)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function resolveRecordedMinutes(int $employeeId, string $date, int $clockOvertimeMinutes): int
    {
        $declared = DeclaredOvertimeDuration::minutesForDate($employeeId, $date);

        return $declared > 0 ? $declared : max(0, $clockOvertimeMinutes);
    }

    private function setRecordedMinutes(OvertimeRequest $request, int $recordedMinutes): void
    {
        $previousRecorded = $request->exists ? (int) $request->recorded_minutes : 0;
        $request->recorded_minutes = $recordedMinutes;

        if (! $request->exists) {
            $request->status = OvertimeRequestStatus::Pending;
            $request->approved_minutes = 0;

            return;
        }

        $status = $request->status;
        $approved = (int) $request->approved_minutes;

        if ($status === OvertimeRequestStatus::Approved) {
            $request->approved_minutes = $approved === $previousRecorded
                ? $recordedMinutes
                : min($approved, $recordedMinutes);
        }
    }
}
