<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StationDeviceStatus;
use App\Enums\StationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttendanceStationRequest;
use App\Http\Requests\Admin\UpdateAttendanceStationRequest;
use App\Models\Attendance;
use App\Models\AttendanceStation;
use App\Models\StationActivityLog;
use App\Services\AuditLogger;
use App\Services\StationBindingService;
use Illuminate\Http\Request;

class AttendanceStationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly StationBindingService $bindings,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', AttendanceStation::class);

        $stations = AttendanceStation::query()
            ->with('activeBindingRelation')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('station_code', 'like', $term)
                        ->orWhere('station_name', 'like', $term)
                        ->orWhere('location', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('station_code')
            ->paginate(15)
            ->withQueryString();

        return view('admin.stations.index', [
            'stations' => $stations,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function create()
    {
        $this->authorize('create', AttendanceStation::class);

        $next = AttendanceStation::query()->count() + 1;

        return view('admin.stations.form', [
            'station' => null,
            'suggestedCode' => 'BACS-STATION-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(StoreAttendanceStationRequest $request)
    {
        $data = $request->validated();
        $station = AttendanceStation::query()->create([
            'station_code' => $data['station_code'],
            'station_name' => $data['station_name'],
            'password' => $data['password'],
            'location' => $data['location'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'device_status' => StationDeviceStatus::Unbound,
            'idle_timeout_minutes' => $data['idle_timeout_minutes'],
            'created_by' => $request->user()->id,
        ]);

        $this->auditLogger->log($request->user(), 'station_created', 'Attendance Stations', $station->id, "Station {$station->station_code} created.");

        return redirect()->route('admin.stations.show', $station)->with('success', 'Attendance station created. It is unbound until the first device logs in.');
    }

    public function show(AttendanceStation $station)
    {
        $this->authorize('view', $station);
        $station->load(['creator', 'bindings' => fn ($q) => $q->latest('bound_at')]);

        $recent = StationActivityLog::query()
            ->with('employee')
            ->where('attendance_station_id', $station->id)
            ->latest('scanned_at')
            ->limit(20)
            ->get();

        return view('admin.stations.show', [
            'station' => $station,
            'binding' => $station->activeBinding(),
            'recent' => $recent,
        ]);
    }

    public function edit(AttendanceStation $station)
    {
        $this->authorize('update', $station);

        return view('admin.stations.form', [
            'station' => $station,
            'suggestedCode' => $station->station_code,
        ]);
    }

    public function update(UpdateAttendanceStationRequest $request, AttendanceStation $station)
    {
        $data = $request->validated();
        $payload = [
            'station_code' => $data['station_code'],
            'station_name' => $data['station_name'],
            'location' => $data['location'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'idle_timeout_minutes' => $data['idle_timeout_minutes'],
        ];

        if (filled($data['password'] ?? null)) {
            $payload['password'] = $data['password'];
        }

        $station->update($payload);
        $this->auditLogger->log($request->user(), 'station_updated', 'Attendance Stations', $station->id, "Station {$station->station_code} updated.");

        return redirect()->route('admin.stations.show', $station)->with('success', 'Station updated.');
    }

    public function activate(Request $request, AttendanceStation $station)
    {
        $this->authorize('update', $station);
        $station->update(['status' => StationStatus::Active]);
        $this->auditLogger->log($request->user(), 'station_activated', 'Attendance Stations', $station->id, "Station {$station->station_code} activated.");

        return back()->with('success', 'Station activated.');
    }

    public function deactivate(Request $request, AttendanceStation $station)
    {
        $this->authorize('update', $station);
        $station->update(['status' => StationStatus::Inactive]);
        $this->auditLogger->log($request->user(), 'station_deactivated', 'Attendance Stations', $station->id, "Station {$station->station_code} deactivated.");

        return back()->with('success', 'Station deactivated.');
    }

    public function lock(Request $request, AttendanceStation $station)
    {
        $this->authorize('update', $station);
        $station->update(['status' => StationStatus::Locked]);
        $this->auditLogger->log($request->user(), 'station_locked', 'Attendance Stations', $station->id, "Station {$station->station_code} locked.");

        return back()->with('success', 'Station locked. The QR scanner will stop recording attendance.');
    }

    public function unlock(Request $request, AttendanceStation $station)
    {
        $this->authorize('update', $station);
        $station->update(['status' => StationStatus::Active]);
        $this->auditLogger->log($request->user(), 'station_unlocked', 'Attendance Stations', $station->id, "Station {$station->station_code} unlocked.");

        return back()->with('success', 'Station unlocked.');
    }

    public function resetPassword(Request $request, AttendanceStation $station)
    {
        $this->authorize('update', $station);
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $station->update([
            'password' => $data['password'],
            'failed_login_attempts' => 0,
            'login_locked_until' => null,
        ]);
        $this->auditLogger->log($request->user(), 'station_password_reset', 'Attendance Stations', $station->id, "Station {$station->station_code} password reset.");

        return back()->with('success', 'Station password has been reset.');
    }

    public function unbind(Request $request, AttendanceStation $station)
    {
        $this->authorize('manageDevice', $station);
        $request->validate(['confirm' => ['accepted']]);

        $this->bindings->unbind($station);
        $this->auditLogger->log($request->user(), 'station_device_reset', 'Attendance Stations', $station->id, "Device binding reset for {$station->station_code}. Station is now unbound.");

        return back()->with('success', 'Device binding reset. The next successful login will bind a new device.');
    }

    public function activity(Request $request, AttendanceStation $station)
    {
        $this->authorize('view', $station);

        $logs = StationActivityLog::query()
            ->with('employee')
            ->where('attendance_station_id', $station->id)
            ->latest('scanned_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.stations.activity', compact('station', 'logs'));
    }

    public function attendance(AttendanceStation $station)
    {
        $this->authorize('view', $station);

        $records = Attendance::query()
            ->with('employee.department')
            ->where(function ($q) use ($station) {
                $q->where('time_in_station_id', $station->id)
                    ->orWhere('time_out_station_id', $station->id);
            })
            ->orderByDesc('attendance_date')
            ->orderByDesc('time_in')
            ->paginate(30);

        return view('admin.stations.attendance', compact('station', 'records'));
    }
}
