<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Services\CalendarService;
use Illuminate\Http\Request;

/**
 * Read-only company calendar for employees.
 *
 * There is deliberately no store/update/destroy here, and every read is
 * filtered through CalendarEvent::scopeVisibleToEmployee, so an employee cannot
 * reach another audience's events by editing the URL or replaying a request.
 */
class CalendarController extends Controller
{
    public function __construct(private readonly CalendarService $calendar) {}

    public function index(Request $request)
    {
        return view('employee.calendar', $this->page($request));
    }

    public function live(Request $request)
    {
        return response()->json($this->calendar->livePayload($this->page($request)));
    }

    /**
     * @return array<string, mixed>
     */
    private function page(Request $request): array
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your account is not linked to an employee profile.');

        $view = $this->calendar->normaliseView($request->query('view'));
        $focus = $this->calendar->focusDate($request->query('date'));

        return $this->calendar->page(
            CalendarEvent::query()->visibleToEmployee($employee),
            $view,
            $focus,
            canManage: false,
            includeInternal: false,
            calendarRoute: 'employee.calendar',
            forEmployee: $employee,
        ) + ['liveUrl' => route('employee.calendar.live')];
    }
}
