<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    public function index(Request $request)
    {
        $date = $request->string('date')->toString() ?: ManilaTime::todayDate();
        $summary = $this->attendanceService->dashboardSummary($date);
        $rows = $this->rosterQuery($request)->paginate(15)->withQueryString();
        $attendance = $this->attendanceMap($date, $rows->getCollection()->pluck('id'));

        return view('admin.dashboard', [
            'summary' => $summary,
            'departmentSummaries' => $this->attendanceService->departmentSummaries($date),
            'rows' => $rows,
            'attendance' => $attendance,
            'departments' => Department::query()->active()->ordered()->get(),
            'employees' => Employee::query()
                ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
                ->orderBy('last_name')
                ->get(),
            'statuses' => AttendanceStatus::cases(),
            'filters' => $request->only(['q', 'department_id', 'employee_id', 'status', 'date']),
            'date' => $date,
        ]);
    }

    public function live(Request $request)
    {
        $date = $request->string('date')->toString() ?: ManilaTime::todayDate();
        $summary = $this->attendanceService->dashboardSummary($date);
        $employees = $this->rosterQuery($request)->limit(50)->get();
        $attendance = $this->attendanceMap($date, $employees->pluck('id'));

        return response()->json([
            'summary' => $summary,
            'departments' => $this->attendanceService->departmentSummaries($date),
            'server_time' => ManilaTime::now()->format('h:i:s A'),
            'rows' => $employees->map(function (Employee $employee) use ($attendance) {
                $row = $attendance->get($employee->id);

                return [
                    'id' => $employee->id,
                    'employee' => $employee->fullName(),
                    'position' => $employee->position,
                    'department' => $employee->department?->name,
                    'time_in' => ManilaTime::formatTime($row?->time_in) ?? '—',
                    'time_out' => ManilaTime::formatTime($row?->time_out) ?? '—',
                    'hours' => $row?->totalHoursLabel() ?? '0:00',
                    'status' => $row?->displayStatus() ?? 'Absent',
                    'status_value' => $row?->status?->value ?? 'absent',
                    'status_color' => $row?->status?->color() ?? 'red',
                ];
            }),
        ]);
    }

    private function rosterQuery(Request $request)
    {
        return Employee::query()
            ->with(['department', 'user', 'workSchedule'])
            ->active()
            ->search($request->string('q')->toString())
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('id', $request->integer('employee_id')))
            ->when($request->filled('status'), function ($q) use ($request) {
                $status = $request->string('status')->toString();
                $date = $request->string('date')->toString() ?: ManilaTime::todayDate();

                if ($status === AttendanceStatus::Absent->value) {
                    $q->whereDoesntHave('attendance', fn ($a) => $a->whereDate('attendance_date', $date)->whereNotNull('time_in'));
                } else {
                    $q->whereHas('attendance', fn ($a) => $a->whereDate('attendance_date', $date)->where('status', $status));
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    private function attendanceMap(string $date, $employeeIds)
    {
        return Attendance::query()
            ->whereDate('attendance_date', $date)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');
    }
}
