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
use Illuminate\Validation\Rule;

class DtrController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    public function index(Request $request)
    {
        $date = $request->string('date')->toString() ?: ManilaTime::todayDate();

        $records = Attendance::query()
            ->with(['employee.department'])
            ->whereDate('attendance_date', $date)
            ->when($request->filled('department_id'), function ($q) use ($request) {
                $q->whereHas('employee', fn ($e) => $e->where('department_id', $request->integer('department_id')));
            })
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->whereHas('employee', fn ($e) => $e->search(trim($term, '%')));
            })
            ->orderBy('employee_id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.dtr.index', [
            'records' => $records,
            'date' => $date,
            'departments' => Department::query()->active()->ordered()->get(),
            'employees' => Employee::query()->orderBy('last_name')->get(),
            'statuses' => AttendanceStatus::cases(),
            'filters' => $request->only(['q', 'department_id', 'employee_id', 'status', 'date']),
        ]);
    }

    public function monthly(Request $request)
    {
        $employeeId = $request->integer('employee_id');
        $year = $request->integer('year') ?: (int) ManilaTime::now()->year;
        $month = $request->integer('month') ?: (int) ManilaTime::now()->month;
        $employee = $employeeId ? Employee::query()->with(['department', 'user'])->findOrFail($employeeId) : null;
        $rows = $employee ? $this->attendanceService->monthlyDtr($employee, $year, $month) : [];

        return view('admin.dtr.monthly', [
            'employee' => $employee,
            'rows' => $rows,
            'year' => $year,
            'month' => $month,
            'employees' => Employee::query()->orderBy('last_name')->get(),
        ]);
    }

    public function show(Attendance $attendance)
    {
        $this->authorize('view', $attendance);
        $attendance->load(['employee.department', 'edits.modifier', 'createdBy']);

        return view('admin.dtr.show', compact('attendance'));
    }

    public function create()
    {
        $this->authorize('create', Attendance::class);

        return view('admin.dtr.create', [
            'employees' => Employee::query()->orderBy('last_name')->get(),
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Attendance::class);

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'attendance_date' => ['required', 'date'],
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $record = $this->attendanceService->createManual($request->user(), $data);

        return redirect()->route('admin.dtr.show', $record)->with('success', 'Manual DTR entry created.');
    }

    public function edit(Attendance $attendance)
    {
        $this->authorize('update', $attendance);
        $attendance->load(['employee', 'edits.modifier']);

        return view('admin.dtr.edit', [
            'attendance' => $attendance,
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function update(Request $request, Attendance $attendance)
    {
        $this->authorize('update', $attendance);

        $data = $request->validate([
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'forced_status' => ['nullable', Rule::in([
                AttendanceStatus::OnLeave->value,
                AttendanceStatus::RestDay->value,
                AttendanceStatus::Holiday->value,
                AttendanceStatus::Absent->value,
            ])],
            'remarks' => ['nullable', 'string', 'max:500'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $record = $this->attendanceService->updateRecord($request->user(), $attendance, $data);

        return redirect()->route('admin.dtr.show', $record)->with('success', 'DTR record updated. The original values were saved in the audit trail.');
    }
}
