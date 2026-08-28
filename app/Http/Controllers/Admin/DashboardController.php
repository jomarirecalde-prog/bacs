<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CalendarEvent;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Services\CalendarService;
use App\Services\DirectoryCatalog;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly CalendarService $calendar,
        private readonly DirectoryCatalog $catalog,
    ) {}

    public function index(Request $request)
    {
        $date = $request->string('date')->toString() ?: ManilaTime::todayDate();
        $snapshot = $this->attendanceService->dashboardSnapshot($date);
        $rows = $this->rosterQuery($request)->paginate(15)->withQueryString();
        $attendance = $this->attendanceMap($date, $rows->getCollection()->pluck('id'));

        return view('admin.dashboard', [
            'summary' => $snapshot['summary'],
            'departmentSummaries' => $snapshot['departments'],
            'rows' => $rows,
            'attendance' => $attendance,
            'departments' => $this->catalog->departments(),
            'employees' => $this->catalog->employeeOptions($request->filled('department_id') ? $request->integer('department_id') : null),
            'statuses' => AttendanceStatus::cases(),
            'filters' => $request->only(['q', 'department_id', 'employee_id', 'status', 'date']),
            'date' => $date,
            'upcomingEvents' => $this->calendar->upcoming(CalendarEvent::query()->published(), days: 90, limit: 6),
            'liveUrl' => route('admin.dashboard.live'),
        ]);
    }

    public function live(Request $request)
    {
        $date = $request->string('date')->toString() ?: ManilaTime::todayDate();
        $snapshot = $this->attendanceService->dashboardSnapshot($date);
        $employees = $this->rosterQuery($request)->limit(50)->get();
        $attendance = $this->attendanceMap($date, $employees->pluck('id'));

        return response()->json([
            'summary' => $snapshot['summary'],
            'departments' => $snapshot['departments'],
            'server_time' => ManilaTime::now()->format('h:i:s A'),
            'rows' => $employees->map(function (Employee $employee) use ($attendance) {
                $row = $attendance->get($employee->id);

                return [
                    'id' => $employee->id,
                    'employee' => $employee->fullName(),
                    'number' => $employee->employee_number,
                    'position' => $employee->position,
                    'department' => $employee->department?->name,
                    'show_url' => route('admin.employees.show', $employee),
                    'am_time_in' => ManilaTime::formatTime($row?->am_time_in) ?? '—',
                    'am_time_out' => ManilaTime::formatTime($row?->am_time_out) ?? '—',
                    'pm_time_in' => ManilaTime::formatTime($row?->pm_time_in) ?? '—',
                    'pm_time_out' => ManilaTime::formatTime($row?->pm_time_out) ?? '—',
                    'overtime' => ManilaTime::formatTime($row?->overtime_in) ?? '—',
                    'time_in' => ManilaTime::formatTime($row?->am_time_in ?? $row?->time_in) ?? '—',
                    'time_out' => ManilaTime::formatTime($row?->pm_time_out ?? $row?->time_out) ?? '—',
                    'hours' => $row?->totalHoursLabel() ?? '0:00',
                    'status' => $row?->displayStatus() ?? 'Absent',
                    'status_value' => $row?->status?->value ?? 'absent',
                    'status_color' => $row?->status?->color() ?? 'red',
                    'working' => (bool) ($row?->hasTimeIn() && ! $row?->isRegularComplete()),
                ];
            }),
        ]);
    }

    private function rosterQuery(Request $request)
    {
        return Employee::query()
            ->with(['department:id,name', 'workSchedule'])
            ->active()
            ->search($request->string('q')->toString())
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('id', $request->integer('employee_id')))
            ->when($request->filled('status'), function ($q) use ($request) {
                $status = $request->string('status')->toString();
                $date = $request->string('date')->toString() ?: ManilaTime::todayDate();

                if ($status === AttendanceStatus::Absent->value) {
                    $q->whereDoesntHave('attendance', fn ($a) => $a->onDate($date)->whereNotNull('time_in'));
                } else {
                    $q->whereHas('attendance', fn ($a) => $a->onDate($date)->where('status', $status));
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    private function attendanceMap(string $date, $employeeIds)
    {
        $ids = collect($employeeIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Attendance::query()
            ->onDate($date)
            ->whereIn('employee_id', $ids)
            ->get()
            ->keyBy('employee_id');
    }
}
