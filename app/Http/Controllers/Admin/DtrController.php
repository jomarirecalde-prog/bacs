<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\AttendanceService;
use App\Services\DirectoryCatalog;
use App\Support\ManilaTime;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DtrController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly DirectoryCatalog $catalog,
    ) {}

    public function index(Request $request)
    {
        $date = $request->string('date')->toString() ?: ManilaTime::todayDate();

        $records = Attendance::query()
            ->with(['employee.department:id,name'])
            ->onDate($date)
            ->when($request->filled('department_id'), function ($q) use ($request) {
                $q->whereIn('employee_id', Employee::query()
                    ->where('department_id', $request->integer('department_id'))
                    ->select('id'));
            })
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->string('q'));
                $q->whereIn('employee_id', Employee::query()->search($term)->select('id'));
            })
            ->orderBy('employee_id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.dtr.index', [
            'records' => $records,
            'date' => $date,
            'departments' => $this->catalog->departments(),
            'employees' => $this->catalog->employeeOptions($request->filled('department_id') ? $request->integer('department_id') : null),
            'statuses' => AttendanceStatus::cases(),
            'filters' => $request->only(['q', 'department_id', 'employee_id', 'status', 'date']),
        ]);
    }

    public function monthly(Request $request)
    {
        $employeeId = $request->integer('employee_id');
        $year = $request->integer('year') ?: (int) ManilaTime::now()->year;
        $month = $request->integer('month') ?: (int) ManilaTime::now()->month;
        $employee = $employeeId ? Employee::query()->with(['department', 'user', 'workSchedule'])->findOrFail($employeeId) : null;
        $rows = $employee ? $this->attendanceService->monthlyDtr($employee, $year, $month) : [];

        return view('admin.dtr.monthly', [
            'employee' => $employee,
            'rows' => $rows,
            'year' => $year,
            'month' => $month,
            'employees' => $this->catalog->employeeOptions(),
            'departments' => $this->catalog->departments(),
            'batchDepartmentId' => $request->integer('department_id') ?: null,
        ]);
    }

    public function monthlyPrint(Request $request)
    {
        $employeeId = $request->integer('employee_id');
        abort_unless($employeeId, 404);

        $year = $request->integer('year') ?: (int) ManilaTime::now()->year;
        $month = $request->integer('month') ?: (int) ManilaTime::now()->month;
        $employee = Employee::query()->with(['department', 'user', 'workSchedule'])->findOrFail($employeeId);
        $rows = $this->attendanceService->monthlyDtr($employee, $year, $month);

        return view('reports.print.monthly-dtr', [
            'employee' => $employee,
            'rows' => $rows,
            'year' => $year,
            'month' => $month,
            'company' => Setting::get('company_name', 'BACS'),
            'address' => Setting::get('company_address', ''),
        ]);
    }

    public function monthlyBatch(Request $request)
    {
        $year = $request->integer('year') ?: (int) ManilaTime::now()->year;
        $month = $request->integer('month') ?: (int) ManilaTime::now()->month;
        $departmentId = $request->integer('department_id') ?: null;
        $employeeIds = array_values(array_filter(array_map('intval', (array) $request->input('employee_ids', []))));

        if (! $departmentId && $employeeIds === []) {
            return redirect()
                ->route('admin.dtr.monthly', ['month' => $month, 'year' => $year])
                ->with('error', 'Choose a department or at least one employee for batch print.');
        }

        $max = (int) config('performance.dtr_batch_print_max', 100);

        $query = Employee::query()
            ->active()
            ->with(['department', 'workSchedule'])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($employeeIds !== []) {
            $query->whereIn('id', $employeeIds);
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $employees = $query->limit($max + 1)->get();

        if ($employees->count() > $max) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', "Batch print is limited to {$max} employees at a time. Narrow the department filter or select fewer employees.");
        }

        if ($employees->isEmpty()) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No active employees matched the selected filters.');
        }

        $sheets = [];
        foreach ($employees as $employee) {
            $sheets[] = [
                'employee' => $employee,
                'rows' => $this->attendanceService->monthlyDtr($employee, $year, $month),
            ];
        }

        $department = $departmentId ? Department::query()->find($departmentId) : null;

        return view('reports.print.monthly-dtr-batch', [
            'sheets' => $sheets,
            'year' => $year,
            'month' => $month,
            'departmentId' => $departmentId,
            'departmentName' => $department?->name,
            'company' => Setting::get('company_name', 'BACS'),
            'address' => Setting::get('company_address', ''),
        ]);
    }

    public function show(Attendance $attendance)
    {
        $this->authorize('view', $attendance);
        $attendance->load(['employee.department', 'edits.modifier', 'createdBy']);

        return view('admin.dtr.show', compact('attendance'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Attendance::class);

        $employeeId = $request->integer('employee_id') ?: null;

        return view('admin.dtr.create', [
            'employees' => $this->catalog->employeeOptions(),
            'statuses' => AttendanceStatus::cases(),
            'preselectedEmployeeId' => $employeeId,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Attendance::class);

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'attendance_date' => ['required', 'date'],
            'am_time_in' => ['nullable', 'date_format:H:i'],
            'am_time_out' => ['nullable', 'date_format:H:i'],
            'pm_time_in' => ['nullable', 'date_format:H:i'],
            'pm_time_out' => ['nullable', 'date_format:H:i'],
            'overtime_in' => ['nullable', 'date_format:H:i'],
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
            'am_time_in' => ['nullable', 'date_format:H:i'],
            'am_time_out' => ['nullable', 'date_format:H:i'],
            'pm_time_in' => ['nullable', 'date_format:H:i'],
            'pm_time_out' => ['nullable', 'date_format:H:i'],
            'overtime_in' => ['nullable', 'date_format:H:i'],
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'forced_status' => ['nullable', Rule::in([
                AttendanceStatus::OnLeave->value,
                AttendanceStatus::TravelOrder->value,
                AttendanceStatus::OfficialBusiness->value,
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
