<?php

namespace App\Http\Controllers\Employee;

use App\Enums\AttendancePunchType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Services\AttendanceCorrectionService;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class AttendanceCorrectionController extends Controller
{
    public function __construct(private readonly AttendanceCorrectionService $corrections) {}

    public function index(Request $request)
    {
        $employee = $this->employee($request);
        $this->authorize('viewAny', AttendanceCorrectionRequest::class);

        $requests = AttendanceCorrectionRequest::query()
            ->ownedBy($employee)
            ->with('reviewer')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('employee.attendance-corrections.index', [
            'requests' => $requests,
            'filters' => $request->only(['status']),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', AttendanceCorrectionRequest::class);
        $employee = $this->employee($request);
        $date = $request->string('date')->toString() ?: ManilaTime::todayDate();

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->onDate($date)
            ->first();

        $originals = [];
        foreach (AttendancePunchType::cases() as $type) {
            $originals[$type->value] = $attendance?->punchValue($type);
        }

        return view('employee.attendance-corrections.create', [
            'employee' => $employee,
            'date' => $date,
            'attendance' => $attendance,
            'originals' => $originals,
            'punchTypes' => AttendancePunchType::cases(),
        ]);
    }

    public function store(StoreAttendanceCorrectionRequest $request)
    {
        $this->authorize('create', AttendanceCorrectionRequest::class);
        $employee = $this->employee($request);

        $record = $this->corrections->submit($employee, [
            'attendance_date' => $request->string('attendance_date')->toString(),
            'punch_type' => $request->string('punch_type')->toString(),
            'requested_time' => $request->string('requested_time')->toString(),
            'reason' => $request->string('reason')->toString(),
        ]);

        return redirect()
            ->route('employee.attendance-corrections.show', $record)
            ->with('success', 'Your correction request was submitted and is pending admin review.');
    }

    public function show(Request $request, AttendanceCorrectionRequest $correction)
    {
        $this->authorize('view', $correction);
        $correction->load(['employee.department', 'reviewer', 'attendance']);

        return view('employee.attendance-corrections.show', [
            'correction' => $correction,
        ]);
    }

    public function cancel(Request $request, AttendanceCorrectionRequest $correction)
    {
        $this->authorize('cancel', $correction);
        $this->corrections->cancel($this->employee($request), $correction);

        return redirect()
            ->route('employee.attendance-corrections.index')
            ->with('success', 'Correction request cancelled.');
    }

    private function employee(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        return $employee;
    }
}
