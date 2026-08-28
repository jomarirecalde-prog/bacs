<?php

namespace App\Services;

use App\Enums\AttendancePunchType;
use App\Enums\StationActivityResult;
use App\Events\AttendanceRecorded;
use App\Models\AttendanceStation;
use App\Models\Employee;
use App\Models\EmployeeQrToken;
use App\Support\ManilaTime;

/**
 * Non-critical work for station scans runs after the HTTP response so the
 * kiosk receives confirmation as soon as the punch is persisted.
 */
class StationScanSideEffects
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
        private readonly EmployeeQrService $qr,
        private readonly StationActivityLogger $activity,
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function defer(array $payload): void
    {
        dispatch(function () use ($payload) {
            $this->run($payload);
        })->afterResponse();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function run(array $payload): void
    {
        $station = isset($payload['station_id'])
            ? AttendanceStation::query()->find($payload['station_id'])
            : null;

        $employee = isset($payload['employee_id'])
            ? Employee::query()->with(['user', 'department'])->find($payload['employee_id'])
            : null;

        $recorded = (bool) ($payload['recorded'] ?? false);

        if ($recorded) {
            $this->runRecordedSideEffects($payload, $station, $employee);
        }

        if (! empty($payload['qr_token_id'])) {
            $token = EmployeeQrToken::query()->find($payload['qr_token_id']);
            if ($token) {
                $this->qr->markUsed($token);
            }
        }

        if ($recorded && $station) {
            $station->update(['last_scan_at' => ManilaTime::now()]);
        }

        if (! empty($payload['activity'])) {
            $this->activity->log(
                $station,
                $payload['activity']['action'] ?? 'scan',
                $recorded ? StationActivityResult::Success : StationActivityResult::Failure,
                employee: $employee,
                failureReason: $recorded ? null : ($payload['code'] ?? null),
                message: $payload['code'] ?? null,
            );
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function runRecordedSideEffects(array $payload, ?AttendanceStation $station, ?Employee $employee): void
    {
        if (! $employee || ! $station) {
            return;
        }

        $attendanceId = $payload['attendance_id'] ?? null;
        $punchType = isset($payload['punch_type']) ? AttendancePunchType::from($payload['punch_type']) : null;
        $recordedAt = isset($payload['recorded_at']) ? ManilaTime::parse($payload['recorded_at']) : ManilaTime::now();
        $date = $payload['date'] ?? $recordedAt->toDateString();

        if ($punchType && $attendanceId) {
            $this->auditLogger->log(
                null,
                'station_'.$punchType->value,
                'Attendance',
                $attendanceId,
                "{$punchType->label()} recorded for {$employee->fullName()} at {$station->station_name} ({$recordedAt->format('h:i A')})."
            );

            if ($employee->user) {
                $this->notifications->notify(
                    $employee->user,
                    $punchType->label().' recorded',
                    'Your '.$punchType->label().' was recorded at '.$recordedAt->format('h:i A').' via '.$station->station_name.'.',
                    'success',
                    route('employee.dashboard')
                );
            }
        }

        if ($punchType === AttendancePunchType::AmTimeIn) {
            $this->attendance->flagPreviousIncompleteFor($employee, $date);
        }

        try {
            broadcast(new AttendanceRecorded(
                $date,
                $employee->id,
                $punchType?->scanCode() ?? ($payload['code'] ?? 'RECORDED'),
                $attendanceId,
            ));
        } catch (\Throwable) {
            // Real-time updates must not break deferred processing.
        }
    }
}
