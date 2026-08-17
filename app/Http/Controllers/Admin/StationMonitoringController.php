<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceStation;
use App\Models\StationActivityLog;
use Illuminate\Http\Request;

class StationMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AttendanceStation::class);

        $stations = AttendanceStation::query()
            ->orderBy('station_code')
            ->get();

        $logs = StationActivityLog::query()
            ->with(['station', 'employee'])
            ->latest('scanned_at')
            ->limit(40)
            ->get();

        return view('admin.stations.monitoring', compact('stations', 'logs'));
    }
}
