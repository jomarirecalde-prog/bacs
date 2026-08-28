<?php

namespace App\Http\Controllers;

use App\Enums\AttendancePunchType;
use App\Services\AttendanceService;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class ClockController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    public function timeIn(Request $request)
    {
        return $this->recordPunch($request);
    }

    public function timeOut(Request $request)
    {
        return $this->recordPunch($request);
    }

    public function recordPunch(Request $request)
    {
        $record = $this->attendanceService->recordNextPunch($request->user());
        $next = $this->attendanceService->nextExpectedFor($request->user()->employee);
        $lastType = $this->lastRecordedType($record, $next);

        $message = ($lastType?->label() ?? 'Attendance').' recorded at '.ManilaTime::formatTime($record->{$lastType?->column() ?? 'am_time_in'}).'.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'attendance' => $this->payload($record, $next),
            ]);
        }

        return back()->with('success', $message);
    }

    public function today(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        $record = $this->attendanceService->todayFor($employee);
        $next = $this->attendanceService->nextExpectedFor($employee);

        return response()->json([
            'ok' => true,
            'now' => ManilaTime::now()->toIso8601String(),
            'attendance' => $record ? $this->payload($record, $next) : null,
            'next_action' => $next?->scanCode(),
            'next_action_label' => $next?->label(),
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

    private function payload($record, ?AttendancePunchType $next): array
    {
        return array_merge($record->punchPayload(), [
            'id' => $record->id,
            'can_record' => $next !== null,
            'next_action' => $next?->scanCode(),
            'next_action_label' => $next?->label(),
            'can_time_in' => $next === AttendancePunchType::AmTimeIn,
            'can_time_out' => $next !== null && $next !== AttendancePunchType::AmTimeIn,
        ]);
    }

    private function lastRecordedType($record, ?AttendancePunchType $next): ?AttendancePunchType
    {
        $last = null;

        foreach (AttendancePunchType::cases() as $type) {
            if ($next && $type === $next) {
                break;
            }

            if ($record->hasPunch($type)) {
                $last = $type;
            }
        }

        if ($last) {
            return $last;
        }

        foreach (array_reverse(AttendancePunchType::cases()) as $type) {
            if ($record->hasPunch($type)) {
                return $type;
            }
        }

        return null;
    }
}
