<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceEffect;
use App\Enums\CalendarEventType;
use App\Enums\EventAudience;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CalendarEventRequest;
use App\Models\CalendarEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Services\CalendarEventService;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    public function __construct(private readonly CalendarEventService $events) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $type = CalendarEventType::tryFrom((string) $request->query('type'));
        $status = EventStatus::tryFrom((string) $request->query('status'));

        $events = CalendarEvent::query()
            ->with(['departments:id,name', 'employees:id,first_name,last_name,full_name', 'creator:id,name'])
            ->search($request->query('q'))
            ->ofType($type?->value)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->filled('from'), fn ($q) => $q->where('end_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('start_date', '<=', $request->query('to')))
            ->orderByDesc('start_date')
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return view('admin.calendar.events.index', [
            'events' => $events,
            'type' => $type,
            'status' => $status,
            'canManage' => $request->user()->can('create', CalendarEvent::class),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', CalendarEvent::class);

        $date = $request->query('date');
        $date = filled($date) ? $this->safeDate($date) : ManilaTime::todayDate();

        return view('admin.calendar.events.form', $this->formData(new CalendarEvent([
            'start_date' => $date,
            'end_date' => $date,
            'event_type' => CalendarEventType::Meeting,
            'audience_type' => EventAudience::All,
            'status' => EventStatus::Published,
            'is_all_day' => true,
        ])));
    }

    public function store(CalendarEventRequest $request)
    {
        $event = $this->events->create(
            $request->payload(),
            $request->departmentIds(),
            $request->employeeIds(),
            $request->user()
        );

        return redirect()
            ->route('admin.calendar.index', ['date' => $event->start_date->toDateString()])
            ->with('success', "Event \"{$event->title}\" was created.");
    }

    public function show(Request $request, CalendarEvent $event)
    {
        $this->authorize('view', $event);

        $event->load(['departments:id,name', 'employees:id,first_name,last_name,full_name,department_id', 'creator:id,name', 'updater:id,name']);

        return view('admin.calendar.events.show', [
            'event' => $event,
            'canManage' => $request->user()->can('update', $event),
        ]);
    }

    public function edit(CalendarEvent $event)
    {
        $this->authorize('update', $event);

        $event->load(['departments:id', 'employees:id']);

        return view('admin.calendar.events.form', $this->formData($event));
    }

    public function update(CalendarEventRequest $request, CalendarEvent $event)
    {
        $event = $this->events->update(
            $event,
            $request->payload(),
            $request->departmentIds(),
            $request->employeeIds(),
            $request->user()
        );

        return redirect()
            ->route('admin.calendar.events.index')
            ->with('success', "Event \"{$event->title}\" was updated.");
    }

    public function destroy(Request $request, CalendarEvent $event)
    {
        $this->authorize('delete', $event);

        $affectedAttendance = $event->affectsAttendance();
        $title = $event->title;

        $this->events->delete($event, $request->user());

        $redirect = redirect()->route('admin.calendar.events.index')
            ->with('success', "Event \"{$title}\" was deleted.");

        if ($affectedAttendance) {
            $redirect->with('warning', 'That event marked its dates as non-working. Those dates are now treated as regular working days in attendance and reports. Existing attendance records were not modified.');
        }

        return $redirect;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(CalendarEvent $event): array
    {
        return [
            'event' => $event,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()
                ->with('department:id,name')
                ->orderBy('full_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name', 'full_name', 'employee_number', 'department_id']),
            'types' => CalendarEventType::cases(),
            'audiences' => EventAudience::cases(),
            'effects' => AttendanceEffect::cases(),
            'statuses' => EventStatus::cases(),
            'selectedDepartments' => old('department_ids', $event->exists ? $event->departments->pluck('id')->all() : []),
            'selectedEmployees' => old('employee_ids', $event->exists ? $event->employees->pluck('id')->all() : []),
        ];
    }

    private function safeDate(string $value): string
    {
        try {
            return ManilaTime::parse($value)->toDateString();
        } catch (\Throwable) {
            return ManilaTime::todayDate();
        }
    }
}
