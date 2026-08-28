<?php

namespace App\Services;

use App\Enums\EventAudience;
use App\Enums\EventStatus;
use App\Models\CalendarEvent;
use App\Models\Employee;
use App\Models\Holiday;
use App\Support\HolidayInfo;
use App\Support\ManilaTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Single point of truth for "is this date a non-working day, and what is it called?".
 *
 * Settings holidays and calendar events are loaded once per month. Employee
 * visibility is applied in memory so dashboard roster loops do not issue
 * two remote queries per employee.
 */
class HolidayResolver
{
    /** @var array<string, array<string, HolidayInfo>> employee cache key => (date => info) */
    private array $months = [];

    /** @var array<string, array<string, HolidayInfo>> */
    private array $settingsMonths = [];

    /** @var array<string, Collection<int, CalendarEvent>> */
    private array $eventMonths = [];

    /** @var array<int, Employee|null> */
    private array $employees = [];

    private ?bool $calendarReady = null;

    public function forDate(string $date, Employee|int|null $for = null): ?HolidayInfo
    {
        $date = ManilaTime::parse($date)->toDateString();

        return $this->month(substr($date, 0, 7), $this->employeeId($for))[$date] ?? null;
    }

    public function isNonWorking(string $date, Employee|int|null $for = null): bool
    {
        return $this->forDate($date, $for) !== null;
    }

    public function nameForDate(string $date, Employee|int|null $for = null): ?string
    {
        return $this->forDate($date, $for)?->name;
    }

    /**
     * @return array<string, HolidayInfo> keyed by Y-m-d
     */
    public function forRange(string $from, string $to, Employee|int|null $for = null): array
    {
        $cursor = ManilaTime::parse($from)->startOfMonth();
        $last = ManilaTime::parse($to)->startOfMonth();
        $fromDate = ManilaTime::parse($from)->toDateString();
        $toDate = ManilaTime::parse($to)->toDateString();
        $employeeId = $this->employeeId($for);

        $out = [];
        while ($cursor->lte($last)) {
            foreach ($this->month($cursor->format('Y-m'), $employeeId) as $date => $info) {
                if ($date >= $fromDate && $date <= $toDate) {
                    $out[$date] = $info;
                }
            }
            $cursor->addMonth();
        }

        ksort($out);

        return $out;
    }

    /**
     * @param  iterable<int, Employee>  $employees
     */
    public function rememberEmployees(iterable $employees): void
    {
        foreach ($employees as $employee) {
            if ($employee instanceof Employee) {
                $this->employees[$employee->id] = $employee;
            }
        }
    }

    /**
     * Drops memoised data. Called whenever a calendar event changes so a
     * single request never observes a stale holiday map.
     */
    public function flush(): void
    {
        $this->months = [];
        $this->settingsMonths = [];
        $this->eventMonths = [];
        $this->employees = [];
    }

    /**
     * @return array<string, HolidayInfo>
     */
    private function month(string $monthKey, ?int $employeeId): array
    {
        $cacheKey = $monthKey.'#'.($employeeId ?? 'global');

        if (isset($this->months[$cacheKey])) {
            return $this->months[$cacheKey];
        }

        $map = $this->settingsHolidaysForMonth($monthKey);
        $employee = $employeeId !== null ? $this->employee($employeeId) : null;
        [$startDate, $endDate] = $this->monthBounds($monthKey);

        foreach ($this->nonWorkingEventsForMonth($monthKey) as $event) {
            if ($employeeId !== null && ! $this->eventVisibleToEmployee($event, $employee)) {
                continue;
            }

            $this->mergeEventIntoMap($event, $map, $startDate, $endDate);
        }

        return $this->months[$cacheKey] = $map;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function monthBounds(string $monthKey): array
    {
        $start = ManilaTime::parse($monthKey.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [$start->toDateString(), $end->toDateString()];
    }

    /**
     * @return array<string, HolidayInfo>
     */
    private function settingsHolidaysForMonth(string $monthKey): array
    {
        if (isset($this->settingsMonths[$monthKey])) {
            return $this->settingsMonths[$monthKey];
        }

        [$startDate, $endDate] = $this->monthBounds($monthKey);
        $map = [];

        Holiday::query()
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->get()
            ->each(function (Holiday $holiday) use (&$map) {
                $date = ManilaTime::parse($holiday->holiday_date)->toDateString();
                $map[$date] = new HolidayInfo(
                    date: $date,
                    name: $holiday->name,
                    source: 'settings',
                );
            });

        return $this->settingsMonths[$monthKey] = $map;
    }

    /**
     * @return Collection<int, CalendarEvent>
     */
    private function nonWorkingEventsForMonth(string $monthKey): Collection
    {
        if (isset($this->eventMonths[$monthKey])) {
            return $this->eventMonths[$monthKey];
        }

        if (! $this->calendarEventsReady()) {
            return $this->eventMonths[$monthKey] = collect();
        }

        [$startDate, $endDate] = $this->monthBounds($monthKey);

        return $this->eventMonths[$monthKey] = CalendarEvent::query()
            ->nonWorking()
            ->overlapping($startDate, $endDate)
            ->with(['departments:id', 'employees:id'])
            ->get();
    }

    /**
     * @param  array<string, HolidayInfo>  $map
     */
    private function mergeEventIntoMap(CalendarEvent $event, array &$map, string $startDate, string $endDate): void
    {
        $cursor = $event->start_date->copy();
        $stop = $event->end_date;

        while ($cursor->lte($stop)) {
            $date = $cursor->toDateString();
            if ($date >= $startDate && $date <= $endDate) {
                $map[$date] = new HolidayInfo(
                    date: $date,
                    name: $event->title,
                    source: 'calendar',
                    effect: $event->attendance_effect,
                    eventId: $event->id,
                );
            }
            $cursor->addDay();
        }
    }

    private function eventVisibleToEmployee(CalendarEvent $event, ?Employee $employee): bool
    {
        if (! in_array($event->status?->value ?? $event->status, EventStatus::employeeVisibleValues(), true)) {
            return false;
        }

        $audience = $event->audience_type instanceof EventAudience
            ? $event->audience_type
            : EventAudience::tryFrom((string) $event->audience_type);

        if ($audience === EventAudience::All) {
            return true;
        }

        if (! $employee) {
            return false;
        }

        if ($audience === EventAudience::Departments) {
            return $employee->department_id
                && $event->departments->contains('id', $employee->department_id);
        }

        if ($audience === EventAudience::Employees) {
            return $event->employees->contains('id', $employee->id);
        }

        return false;
    }

    private function calendarEventsReady(): bool
    {
        return $this->calendarReady ??= Cache::remember('schema:calendar_events', 3600, function () {
            return Schema::hasTable('calendar_events');
        });
    }

    private function employeeId(Employee|int|null $for): ?int
    {
        if ($for instanceof Employee) {
            $this->employees[$for->id] = $for;

            return $for->id;
        }

        return $for;
    }

    private function employee(int $id): ?Employee
    {
        if (! array_key_exists($id, $this->employees)) {
            $this->employees[$id] = Employee::query()->find($id);
        }

        return $this->employees[$id];
    }
}
