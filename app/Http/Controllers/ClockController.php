<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class ClockController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    public function timeIn(Request $request)
    {
        $record = $this->attendanceService->clockIn($request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Time In recorded at '.ManilaTime::formatTime($record->time_in).'.',
                'attendance' => $this->payload($record),
            ]);
        }

        return back()->with('success', 'Time In recorded at '.ManilaTime::formatTime($record->time_in).'.');
    }

    public function timeOut(Request $request)
    {
        $record = $this->attendanceService->clockOut($request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Time Out recorded at '.ManilaTime::formatTime($record->time_out).'.',
                'attendance' => $this->payload($record),
            ]);
        }

        return back()->with('success', 'Time Out recorded at '.ManilaTime::formatTime($record->time_out).'.');
    }

    public function today(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $record = $this->attendanceService->todayFor($employee);

        return response()->json([
            'ok' => true,
            'now' => ManilaTime::now()->toIso8601String(),
            'attendance' => $record ? $this->payload($record) : null,
        ]);
    }

    public function serverTime()
    {
        $now = ManilaTime::now();

        return response()->json([
            'iso' => $now->toIso8601String(),
            'timestamp' => $now->getTimestampMs(),
            'date' => $now->toFormattedDateString(),
            'time' => $now->format('h:i:s A'),
            'timezone' => ManilaTime::TIMEZONE,
        ]);
    }

    private function payload($record): array
    {
        return [
            'id' => $record->id,
            'date' => $record->attendance_date->toDateString(),
            'time_in' => ManilaTime::formatTime($record->time_in),
            'time_out' => ManilaTime::formatTime($record->time_out),
            'status' => $record->displayStatus(),
            'status_value' => $record->status?->value,
            'late_minutes' => $record->late_minutes,
            'undertime_minutes' => $record->undertime_minutes,
            'overtime_minutes' => $record->overtime_minutes,
            'total_hours' => $record->totalHoursLabel(),
            'can_time_in' => ! $record->hasTimeIn(),
            'can_time_out' => $record->hasTimeIn() && ! $record->hasTimeOut(),
        ];
    }
}
