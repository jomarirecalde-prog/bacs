<?php

namespace App\Http\Controllers\Station;

use App\Enums\StationActivityResult;
use App\Enums\StationStatus;
use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Services\EmployeeQrService;
use App\Services\StationActivityLogger;
use App\Support\ManilaTime;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ScanController extends Controller
{
    public function __construct(
        private readonly EmployeeQrService $qr,
        private readonly AttendanceService $attendance,
        private readonly StationActivityLogger $activity,
    ) {}

    public function store(Request $request)
    {
        $station = $request->user('station')?->fresh();
        $request->validate(['token' => ['required', 'string', 'max:200']]);

        if (! $station || $station->status !== StationStatus::Active) {
            $this->activity->log($station, 'scan', StationActivityResult::Failure, $request, failureReason: 'station_locked');

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
            $this->activity->log($station, 'scan', StationActivityResult::Failure, $request, failureReason: $disabled ? 'qr_disabled' : 'invalid_qr');

            return response()->json($this->errorPayload(
                $disabled ? 'QR_DISABLED' : 'INVALID_QR',
                $disabled ? 'QR Code Disabled' : 'Invalid QR Code',
                $disabled ? $message : 'This QR code is not registered in the BACS DTR System.'
            ), 422);
        }

        $employee = $qrToken->employee;

        if (! $employee || ! $employee->user || ! $employee->user->isActive()) {
            $this->activity->log($station, 'scan', StationActivityResult::Failure, $request, employee: $employee, failureReason: 'inactive_account');

            return response()->json($this->errorPayload(
                'ACCOUNT_INACTIVE',
                'Account Inactive',
                'Please contact the administrator.',
                $employee
            ), 422);
        }

        $result = $this->attendance->recordFromStation($station, $employee);
        $this->qr->markUsed($qrToken);

        $attendance = $result['attendance'];
        $code = $result['code'];
        $recorded = $result['recorded'];

        $this->activity->log(
            $station,
            $result['action'] ?? strtolower($code),
            $recorded ? StationActivityResult::Success : StationActivityResult::Failure,
            $request,
            employee: $employee,
            failureReason: $recorded ? null : $code,
            message: $code
        );

        if ($recorded) {
            $station->update(['last_scan_at' => ManilaTime::now()]);
        }

        $title = match ($code) {
            'TIME_IN' => 'Time In Successful',
            'TIME_OUT' => 'Time Out Successful',
            'ALREADY_TIMED_IN' => 'Already Timed In',
            'ATTENDANCE_COMPLETED' => 'Attendance Completed',
            default => 'Attendance',
        };

        $message = match ($code) {
            'TIME_IN' => 'Time In recorded.',
            'TIME_OUT' => 'Time Out recorded.',
            'ALREADY_TIMED_IN' => 'You have already recorded your Time In.',
            'ATTENDANCE_COMPLETED' => 'Your attendance for today is already complete.',
            default => 'Attendance processed.',
        };

        return response()->json([
            'ok' => $recorded,
            'code' => $code,
            'title' => $title,
            'message' => $message,
            'action' => $result['action'],
            'employee' => [
                'name' => $employee->fullName(),
                'employee_number' => $employee->employee_number,
                'department' => $employee->department?->name,
                'position' => $employee->position,
                'photo' => $employee->photoUrl(),
            ],
            'attendance' => [
                'time_in' => ManilaTime::formatTime($attendance->time_in),
                'time_out' => ManilaTime::formatTime($attendance->time_out),
                'status' => $attendance->status?->label(),
                'late_minutes' => $attendance->late_minutes,
            ],
            'time' => $code === 'TIME_OUT'
                ? ManilaTime::formatTime($attendance->time_out)
                : ManilaTime::formatTime($attendance->time_in),
        ]);
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
