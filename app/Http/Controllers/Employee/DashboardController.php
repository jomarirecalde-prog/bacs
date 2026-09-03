<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Services\AttendanceService;
use App\Services\CalendarService;
use App\Services\HolidayResolver;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly CalendarService $calendar,
        private readonly HolidayResolver $holidays,
    ) {}

    public function index(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your account is not linked to an employee profile.');

        $employee->loadMissing(['department', 'workSchedule']);
        $today = $this->attendanceService->todayFor($employee);
        $nextAction = $this->attendanceService->nextExpectedFor($employee, $today);
        $month = (int) ManilaTime::now()->month;
        $year = (int) ManilaTime::now()->year;
        // One batched month query (not N punch lookups). Empty days use the
        // lightweight status path — no full calculator pass per missing day.
        $monthly = $this->attendanceService->monthlyDtr($employee, $year, $month);

        return view('employee.dashboard', [
            'employee' => $employee,
            'today' => $today,
            'monthly' => $monthly,
            'month' => $month,
            'year' => $year,
            'nextAction' => $nextAction,
            'canRecord' => $nextAction !== null,
            'canTimeIn' => $nextAction !== null,
            'canTimeOut' => $nextAction !== null,
            'upcomingEvents' => $this->calendar->upcoming(
                CalendarEvent::query()->visibleToEmployee($employee),
                days: 90,
                limit: 4
            ),
            'todayHoliday' => $this->holidays->forDate(ManilaTime::todayDate(), $employee),
        ]);
    }
}
