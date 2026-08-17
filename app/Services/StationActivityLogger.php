<?php

namespace App\Services;

use App\Enums\StationActivityResult;
use App\Models\AttendanceStation;
use App\Models\Employee;
use App\Models\StationActivityLog;
use App\Support\ManilaTime;
use App\Support\SecureHash;
use Illuminate\Http\Request;

class StationActivityLogger
{
    public function log(
        ?AttendanceStation $station,
        string $action,
        StationActivityResult $result,
        ?Request $request = null,
        ?Employee $employee = null,
        ?string $failureReason = null,
        ?string $message = null,
        ?string $deviceHash = null,
    ): StationActivityLog {
        $request ??= request();
        $secrets = app(StationBindingService::class)->secretsFromRequest($request);

        return StationActivityLog::query()->create([
            'attendance_station_id' => $station?->id,
            'employee_id' => $employee?->id,
            'action' => $action,
            'result' => $result->value,
            'failure_reason' => $failureReason,
            'message' => $message,
            'ip_address' => $request?->ip(),
            'device_identifier_hash' => $deviceHash ?? ($secrets ? SecureHash::make($secrets['device_identifier']) : null),
            'scanned_at' => ManilaTime::now(),
            'created_at' => ManilaTime::now(),
        ]);
    }
}
