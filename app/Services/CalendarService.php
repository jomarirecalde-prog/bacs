<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Employee;
use App\Support\CalendarEventPresenter;
use App\Support\ManilaTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds the date scaffolding for the month / week / day / agenda views.
 *
 * Every period is computed in Asia/Manila so the calendar always agrees with
 * the attendance clock.
 */
class CalendarService
{
    public const VIEWS = ['month', 'week', 'day', 'agenda'];

    public function __construct(private readonly HolidayResolver $holidays) {}

    public function normaliseView(?string $view): string
    {
        return in_array($view, self::VIEWS, true) ? $view : 'month';
    }

    public function focusDate(?string $date): Carbon
    {
        try {
            return filled($date) ? ManilaTime::parse($date)->startOfDay() : ManilaTime::today();
        } catch (\Throwable) {
            return ManilaTime::today();
        }
    }

    /**
     * The visible window for a view, plus the navigation targets and heading.
     *
     * @return array{start: Carbon, end: Carbon, label: string, prev: string, next: string}
     */
    public function period(string $view, Carbon $focus): array
    {
        return match ($view) {
            'week' => [
                'start' => $focus->copy()->startOfWeek(Carbon::SUNDAY),
                'end' => $focus->copy()->endOfWeek(Carbon::SATURDAY),
                'label' => $this->weekLabel($focus),
                'prev' => $focus->copy()->subWeek()->toDateString(),
                'next' => $focus->copy()->addWeek()->toDateString(),
            ],
            'day' => [
                'start' => $focus->copy()->startOfDay(),
                'end' => $focus->copy()->endOfDay(),
                'label' => $focus->format('l, F j, Y'),
                'prev' => $focus->copy()->subDay()->toDateString(),
                'next' => $focus->copy()->addDay()->toDateString(),
            ],
            default => [
                'start' => $focus->copy()->startOfMonth(),
                'end' => $focus->copy()->endOfMonth(),
                'label' => $focus->format('F Y'),
                'prev' => $focus->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'next' => $focus->copy()->addMonthNoOverflow()->startOfMonth()->toDateString(),
            ],
        };
    }

    private function weekLabel(Carbon $focus): string
    {
        $start = $focus->copy()->startOfWeek(Carbon::SUNDAY);
        $end = $focus->copy()->endOfWeek(Carbon::SATURDAY);

        if ($start->isSameMonth($end)) {
            return $start->format('F j').' – '.$end->format('j, Y');
        }

        return $start->format('M j').' – '.$end->format('M j, Y');
    }

    /**
     * Loads every event intersecting the window. The caller supplies an already
     * scoped query so employee visibility is enforced at the source.
     */
    public function load(Builder $query, Carbon $start, Carbon $end): Collection
    {
        return $query->with([
            'departments:id,name',
            'employees:id,first_name,last_name,full_name',
            // Eager-loaded because the admin details payload reports authorship.
            'creator:id,name',
        ])
            ->overlapping($start->toDateString(), $end->toDateString())
            ->orderBy('start_date')
            ->orderByRaw('CASE WHEN is_all_day = 1 THEN 0 ELSE 1 END')
            ->orderBy('start_time')
            ->orderBy('title')
            ->get();
    }

    /**
     * Expands multi-day events so they appear on each date they cover.
     *
     * @return array<string, Collection<int, CalendarEvent>>
     */
    public function groupByDate(Collection $events, Carbon $start, Carbon $end): array
    {
        $buckets = [];
        $cursor = $start->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $buckets[$cursor->toDateString()] = collect();
            $cursor->addDay();
        }

        foreach ($events as $event) {
            $day = $event->start_date->copy();
            while ($day->lte($event->end_date)) {
                $key = $day->toDateString();
                if (isset($buckets[$key])) {
                    $buckets[$key]->push($event);
                }
                $day->addDay();
            }
        }

        return $buckets;
    }

    /**
     * A 6-row month grid padded to whole weeks, Sunday first.
     *
     * @param  array<string, Collection>  $byDate
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function monthGrid(Carbon $focus, array $byDate, Employee|int|null $forEmployee = null): array
    {
        $gridStart = $focus->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $focus->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $today = ManilaTime::todayDate();
        $month = $focus->month;

        $weeks = [];
        $week = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $date = $cursor->toDateString();
            $holiday = $this->holidays->forDate($date, $forEmployee);

            $week[] = [
                'date' => $date,
                'day' => $cursor->day,
                'in_month' => $cursor->month === $month,
                'is_today' => $date === $today,
                'is_weekend' => $cursor->isWeekend(),
                'holiday' => $holiday,
                'events' => $byDate[$date] ?? collect(),
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $cursor->addDay();
        }

        return $weeks;
    }

    /**
     * Day cells for the week view, in the same shape as the month grid cells.
     *
     * @param  array<string, Collection>  $byDate
     * @return array<int, array<string, mixed>>
     */
    public function weekDays(Carbon $focus, array $byDate, Employee|int|null $forEmployee = null): array
    {
        $cursor = $focus->copy()->startOfWeek(Carbon::SUNDAY);
        $today = ManilaTime::todayDate();
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $cursor->toDateString();
            $days[] = [
                'date' => $date,
                'day' => $cursor->day,
                'weekday' => $cursor->format('D'),
                'is_today' => $date === $today,
                'is_weekend' => $cursor->isWeekend(),
                'holiday' => $this->holidays->forDate($date, $forEmployee),
                'events' => $byDate[$date] ?? collect(),
            ];
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Chronological day buckets that actually contain events, for the agenda view.
     *
     * @param  array<string, Collection>  $byDate
     * @return array<int, array<string, mixed>>
     */
    public function agenda(array $byDate, Employee|int|null $forEmployee = null): array
    {
        $today = ManilaTime::todayDate();
        $rows = [];

        foreach ($byDate as $date => $events) {
            if ($events->isEmpty()) {
                continue;
            }

            $day = ManilaTime::parse($date);
            $rows[] = [
                'date' => $date,
                'label' => $day->format('l, F j, Y'),
                'short' => $day->format('D'),
                'day' => $day->day,
                'is_today' => $date === $today,
                'is_past' => $date < $today,
                'holiday' => $this->holidays->forDate($date, $forEmployee),
                'events' => $events,
            ];
        }

        return $rows;
    }

    /**
     * Upcoming events from today forward, for dashboard summaries.
     */
    public function upcoming(Builder $query, int $days = 60, int $limit = 5): Collection
    {
        $today = ManilaTime::todayDate();
        $until = ManilaTime::today()->addDays($days)->toDateString();

        return $query->overlapping($today, $until)
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }

    /**
     * Shared view-model for the admin and employee calendars, including the
     * HTML fragment used to refresh an open calendar over the WebSocket.
     *
     * @return array<string, mixed>
     */
    public function page(
        Builder $query,
        string $view,
        Carbon $focus,
        bool $canManage,
        bool $includeInternal,
        string $calendarRoute,
        $typeFilter = null,
        Employee|int|null $forEmployee = null,
    ): array {
        $period = $this->period($view, $focus);
        $events = $this->load($query, $period['start'], $period['end']);
        $byDate = $this->groupByDate($events, $period['start'], $period['end']);

        return [
            'view' => $view,
            'focus' => $focus,
            'period' => $period,
            'typeFilter' => $typeFilter,
            'weeks' => $view === 'month' ? $this->monthGrid($focus, $byDate, $forEmployee) : [],
            'weekDays' => $view === 'week' ? $this->weekDays($focus, $byDate, $forEmployee) : [],
            'agenda' => $view === 'agenda' ? $this->agenda($byDate, $forEmployee) : [],
            'dayEvents' => $view === 'day' ? ($byDate[$focus->toDateString()] ?? collect()) : collect(),
            'dayHoliday' => $view === 'day' ? $this->holidays->forDate($focus->toDateString(), $forEmployee) : null,
            'eventCount' => $events->count(),
            'canManage' => $canManage,
            'calendarRoute' => $calendarRoute,
            'modalEvents' => $events->mapWithKeys(fn (CalendarEvent $event) => [
                $event->id => CalendarEventPresenter::forModal($event, $includeInternal, $canManage),
            ]),
            'today' => ManilaTime::todayDate(),
        ];
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array{events: mixed, eventCount: int, html: string, start: string, end: string}
     */
    public function livePayload(array $page): array
    {
        $view = $page['view'];

        return [
            'events' => $page['modalEvents'],
            'eventCount' => $page['eventCount'],
            'start' => $page['period']['start']->toDateString(),
            'end' => $page['period']['end']->toDateString(),
            'html' => view('calendar.partials.'.$view, $page)->render(),
        ];
    }
}
