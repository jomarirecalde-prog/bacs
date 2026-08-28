<?php

namespace App\Http\Controllers\Station;

use App\Enums\AttendancePunchType;
use App\Enums\StationActivityResult;
use App\Enums\StationStatus;
use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Services\EmployeeQrService;
use App\Services\StationScanSideEffects;
use App\Support\ManilaTime;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ScanController extends Controller
{
    public function __construct(
        private readonly EmployeeQrService $qr,
        private readonly AttendanceService $attendance,
        private readonly StationScanSideEffects $sideEffects,
    ) {}

    public function store(Request $request)
    {
        $station = $request->user('station');
        $request->validate(['token' => ['required', 'string', 'max:200']]);

        if (! $station || $station->status !== StationStatus::Active) {
            $this->deferActivity($request, $station, null, null, 'scan', StationActivityResult::Failure, 'station_locked');

            return response()->json($this->errorPayload(
                'STATION_LOCKED',
                'Station Locked',
                'This attendance station has been temporarily disabled. Please contact the administrator.'
            ), 403);
        }

        try {
            $qrToken = $this->qr->resolve((string) $request->input('token'));
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'This QR code is not registered in the BACS DTR System.';
            $disabled = str_contains(strtolower($message), 'disabled');
            $this->deferActivity(
                $request,
                $station,
                null,
                null,
                'scan',
                StationActivityResult::Failure,
                $disabled ? 'qr_disabled' : 'invalid_qr'
            );

            return response()->json($this->errorPayload(
                $disabled ? 'QR_DISABLED' : 'INVALID_QR',
                $disabled ? 'QR Code Disabled' : 'Invalid QR Code',
                $disabled ? $message : 'This QR code is not registered in the BACS DTR System.'
            ), 422);
        }

        $employee = $qrToken->employee;

        if (! $employee || ! $employee->user || ! $employee->user->isActive()) {
            $this->deferActivity($request, $station, $employee, null, 'scan', StationActivityResult::Failure, 'inactive_account');

            return response()->json($this->errorPayload(
                'ACCOUNT_INACTIVE',
                'Account Inactive',
                'Please contact the administrator.',
                $employee
            ), 422);
        }

        $result = $this->attendance->recordFromStation($station, $employee);
        $code = $result['code'];
        $recorded = $result['recorded'];
        $attendance = $result['attendance'];

        $this->sideEffects->defer([
            'station_id' => $station->id,
            'employee_id' => $employee->id,
            'qr_token_id' => $qrToken->id,
            'recorded' => $recorded,
            'code' => $code,
            'date' => ManilaTime::todayDate(),
            'attendance_id' => $recorded ? $attendance->id : null,
            'punch_type' => $result['punch_type'] ?? null,
            'recorded_at' => $recorded ? ($result['recorded_at'] ?? ManilaTime::now())->toIso8601String() : null,
            'activity' => [
                'action' => $result['action'] ?? strtolower($code),
            ],
        ]);

        return response()->json($this->scanPayload($result, $employee, $code, $recorded));
    }

    private function deferActivity(
        Request $request,
        $station,
        $employee,
        ?int $qrTokenId,
        string $action,
        StationActivityResult $result,
        string $failureReason,
    ): void {
        $this->sideEffects->defer([
            'station_id' => $station?->id,
            'employee_id' => $employee?->id,
            'qr_token_id' => $qrTokenId,
            'recorded' => $result === StationActivityResult::Success,
            'code' => $failureReason,
            'activity' => ['action' => $action],
        ]);
    }

    private function scanPayload(array $result, $employee, string $code, bool $recorded): array
    {
        $attendance = $result['attendance'];
        $actionLabel = $result['action_label'] ?? null;
        $nextLabel = $result['next_action_label'] ?? null;

        $title = match (true) {
            $recorded => 'Attendance Recorded',
            $code === 'DUPLICATE_SCAN' => 'Duplicate Scan',
            $code === 'PENDING_CORRECTION' => 'Correction Pending',
            $code === 'INVALID_SEQUENCE' => 'Invalid Sequence',
            $code === 'INVALID_SCAN_TIME' => 'Invalid Scan Time',
            $code === 'OVERTIME_NOT_ALLOWED' => 'Overtime Not Allowed',
            $code === 'ATTENDANCE_COMPLETED' => 'Attendance Completed',
            default => 'Attendance',
        };

        $message = match ($code) {
            'AM_TIME_IN', 'AM_TIME_OUT', 'PM_TIME_IN', 'PM_TIME_OUT', 'OVERTIME' => $actionLabel.' recorded.',
            'DUPLICATE_SCAN' => ($actionLabel ?? 'This punch').' has already been recorded.',
            'PENDING_CORRECTION' => $result['message'] ?? 'You have a pending DTR correction request for today.',
            'INVALID_SEQUENCE' => 'A required attendance entry is missing. Please contact your administrator.',
            'INVALID_SCAN_TIME' => 'This scan is outside the allowed time window.',
            'OVERTIME_NOT_ALLOWED' => 'Overtime is not allowed at this time.',
            'ATTENDANCE_COMPLETED' => 'Your attendance for today is already complete.',
            default => 'Attendance processed.',
        };

        $progress = $this->attendanceProgress($attendance);

        return [
            'ok' => $recorded,
            'code' => $code,
            'title' => $title,
            'message' => $message,
            'action' => $result['action'],
            'action_label' => $actionLabel,
            'next_action' => $result['next_action'],
            'next_action_label' => $nextLabel,
            'employee' => [
                'name' => $employee->fullName(),
                'employee_number' => $employee->employee_number,
                'department' => $employee->department?->name,
                'position' => $employee->position,
                'photo' => $employee->photoUrl(),
            ],
            'attendance' => array_merge($attendance->punchPayload(), [
                'progress' => $progress,
            ]),
            'time' => $recorded ? ManilaTime::formatTime($result['recorded_at'] ?? null) : null,
            'date' => ManilaTime::todayDate(),
        ];
    }

    /** @return list<array{label: string, value: ?string, done: bool}> */
    private function attendanceProgress($attendance): array
    {
        $items = [];

        foreach (AttendancePunchType::cases() as $type) {
            if ($type === AttendancePunchType::Overtime && ! $attendance->isRegularComplete()) {
                continue;
            }

            $value = $attendance->punchValue($type);
            $items[] = [
                'label' => $type->label(),
                'value' => $value ? ManilaTime::formatTime($value) : null,
                'done' => $value !== null,
            ];
        }

        return $items;
    }

    private function errorPayload(string $code, string $title, string $message, $employee = null): array
    {
        return [
            'ok' => false,
            'code' => $code,
            'title' => $title,
            'message' => $message,
            'employee' => $employee ? [
                'name' => $employee->fullName(),
                'employee_number' => $employee->employee_number,
                'department' => $employee->department?->name,
                'position' => $employee->position,
                'photo' => $employee->photoUrl(),
            ] : null,
        ];
    }
}
