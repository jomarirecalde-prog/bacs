<?php

namespace App\Services;

use App\Models\AttendanceStation;
use App\Models\Employee;
use App\Support\ManilaTime;
use Illuminate\Support\Facades\Cache;

class QrCrossStationGuard
{
    private const CACHE_PREFIX = 'attendance:qr:last_station:';

    /**
     * @return array{code: string, title: string, message: string}|null
     */
    public function blockReason(Employee $employee, AttendanceStation $station): ?array
    {
        $window = (int) config('attendance.qr_cross_station_window_seconds', 90);
        if ($window <= 0) {
            return null;
        }

        $last = Cache::get(self::CACHE_PREFIX.$employee->id);
        if (! is_array($last)) {
            return null;
        }

        $lastStationId = (int) ($last['station_id'] ?? 0);
        if ($lastStationId <= 0 || $lastStationId === (int) $station->id) {
            return null;
        }

        $elapsed = ManilaTime::now()->timestamp - (int) ($last['at'] ?? 0);
        if ($elapsed >= $window) {
            return null;
        }

        return [
            'code' => 'CROSS_STATION_SCAN',
            'title' => 'Scan Not Accepted',
            'message' => 'This employee recently scanned at another attendance station. Please wait a moment and try again at one station only.',
        ];
    }

    public function rememberSuccessfulScan(Employee $employee, AttendanceStation $station): void
    {
        $window = (int) config('attendance.qr_cross_station_window_seconds', 90);
        if ($window <= 0) {
            return;
        }

        Cache::put(self::CACHE_PREFIX.$employee->id, [
            'station_id' => $station->id,
            'at' => ManilaTime::now()->timestamp,
        ], now()->addMinutes(max(5, (int) ceil($window / 60) + 1)));
    }
}
