<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    public function index(Request $request)
    {
        $employee = $this->ownEmployee($request);
        $from = $request->input('date_from') ?: ManilaTime::today()->startOfMonth()->toDateString();
        $to = $request->input('date_to') ?: ManilaTime::todayDate();

        $records = Attendance::query()
            ->where('employee_id', $employee->id)
            ->betweenDates($from, $to)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('attendance_date')
            ->paginate(20)
            ->withQueryString();

        return view('employee.attendance', [
            'employee' => $employee,
            'records' => $records,
            'today' => $this->attendanceService->todayFor($employee),
            'dateFrom' => $from,
            'dateTo' => $to,
        ]);
    }

    private function ownEmployee(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);

        return $employee;
    }
}
