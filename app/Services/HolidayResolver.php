<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Employee;
use App\Models\Holiday;
use App\Support\HolidayInfo;
use App\Support\ManilaTime;

/**
 * Single point of truth for "is this date a non-working day, and what is it called?".
 *
 * Unions two sources so the calendar module drives attendance without the
 * legacy holidays table (and the tests around it) having to change:
 *   1. calendar_events — holiday / special non-working events with a
 *      non-informational attendance effect
 *   2. holidays — the original Settings-managed list
 *
 * Calendar events honour audience. Settings holidays remain company-wide.
 * Lookups are resolved a whole month at a time and memoised per viewer, so
 * iterating a monthly DTR costs two queries rather than two per day.
 */
class HolidayResolver
{
    /** @var array<string, array<string, HolidayInfo>> cache key => (date => info) */
    private array $months = [];

    /** @var array<int, Employee|null> */
    private array $employees = [];

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
     * Drops memoised data. Called whenever a calendar event changes so a
     * single request never observes a stale holiday map.
     */
    public function flush(): void
    {
        $this->months = [];
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

        $start = ManilaTime::parse($monthKey.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $map = [];

        // Legacy settings-managed holidays first; calendar events may refine them.
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

        $query = CalendarEvent::query()
            ->nonWorking()
            ->overlapping($startDate, $endDate);

        if ($employeeId !== null) {
            $query->visibleToEmployee($this->employee($employeeId));
        }

        $query->get()->each(function (CalendarEvent $event) use (&$map, $startDate, $endDate) {
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
        });

        return $this->months[$cacheKey] = $map;
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
