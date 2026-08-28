<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CalendarEventType;
use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Services\CalendarService;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(private readonly CalendarService $calendar) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', CalendarEvent::class);

        return view('admin.calendar.index', $this->page($request));
    }

    public function live(Request $request)
    {
        $this->authorize('viewAny', CalendarEvent::class);

        return response()->json($this->calendar->livePayload($this->page($request)));
    }

    /**
     * @return array<string, mixed>
     */
    private function page(Request $request): array
    {
        $view = $this->calendar->normaliseView($request->query('view'));
        $focus = $this->calendar->focusDate($request->query('date'));
        $typeFilter = CalendarEventType::tryFrom((string) $request->query('type'));
        $canManage = $request->user()->can('create', CalendarEvent::class);

        return $this->calendar->page(
            CalendarEvent::query()->ofType($typeFilter?->value),
            $view,
            $focus,
            $canManage,
            includeInternal: true,
            calendarRoute: 'admin.calendar.index',
            typeFilter: $typeFilter,
        ) + ['liveUrl' => route('admin.calendar.live')];
    }
}
