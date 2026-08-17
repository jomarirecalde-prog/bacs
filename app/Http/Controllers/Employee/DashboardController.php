<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your account is not linked to an employee profile.');

        $today = $this->attendanceService->todayFor($employee);
        $month = (int) ManilaTime::now()->month;
        $year = (int) ManilaTime::now()->year;
        $monthly = $this->attendanceService->monthlyDtr($employee, $year, $month);

        return view('employee.dashboard', [
            'employee' => $employee->load(['department', 'workSchedule']),
            'today' => $today,
            'monthly' => $monthly,
            'month' => $month,
            'year' => $year,
            'canTimeIn' => ! $today?->hasTimeIn(),
            'canTimeOut' => $today?->hasTimeIn() && ! $today?->hasTimeOut(),
        ]);
    }
}
