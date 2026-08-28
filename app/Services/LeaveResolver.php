<?php

namespace App\Services;

use App\Models\Leave;
use App\Support\ManilaTime;
use Illuminate\Support\Collection;

/**
 * Request-scoped leave lookup so dashboard and monthly DTR loops do not
 * issue one query per employee or per day.
 */
class LeaveResolver
{
    /** @var array<string, array<int, Leave|false>> date => employee_id => leave|miss */
    private array $hits = [];

    /** @var array<string, true> */
    private array $loaded = [];

    public function approvedOn(int $employeeId, string $date): ?Leave
    {
        $date = ManilaTime::parse($date)->toDateString();

        if (! $this->has($employeeId, $date)) {
            $start = ManilaTime::parse($date)->startOfMonth()->toDateString();
            $end = ManilaTime::parse($date)->endOfMonth()->toDateString();
            $this->loadForEmployee($employeeId, $start, $end);
        }

        $hit = $this->hits[$date][$employeeId] ?? false;

        return $hit instanceof Leave ? $hit : null;
    }

    /**
     * One query covering every given employee on a single date.
     *
     * @param  iterable<int|string>  $employeeIds
     */
    public function loadForDate(iterable $employeeIds, string $date): void
    {
        $date = ManilaTime::parse($date)->toDateString();
        $ids = Collection::make($employeeIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $key = 'date:'.$date;

        if (isset($this->loaded[$key]) || $ids->isEmpty()) {
            $this->loaded[$key] = true;

            return;
        }

        $this->loaded[$key] = true;

        Leave::query()
            ->where('status', 'approved')
            ->whereIn('employee_id', $ids->all())
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->get()
            ->each(function (Leave $leave) use ($date) {
                $this->hits[$date][$leave->employee_id] = $leave;
            });

        foreach ($ids as $id) {
            $this->hits[$date][$id] ??= false;
        }
    }

    public function loadForEmployee(int $employeeId, string $from, string $to): void
    {
        $from = ManilaTime::parse($from)->toDateString();
        $to = ManilaTime::parse($to)->toDateString();
        $key = "emp:{$employeeId}:{$from}:{$to}";

        if (isset($this->loaded[$key])) {
            return;
        }

        $this->loaded[$key] = true;

        $leaves = Leave::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->get();

        $cursor = ManilaTime::parse($from);
        $end = ManilaTime::parse($to);

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $match = $leaves->first(
                fn (Leave $leave) => $leave->start_date->toDateString() <= $date
                    && $leave->end_date->toDateString() >= $date
            );
            $this->hits[$date][$employeeId] = $match ?: false;
            $cursor->addDay();
        }
    }

    private function has(int $employeeId, string $date): bool
    {
        return array_key_exists($date, $this->hits)
            && array_key_exists($employeeId, $this->hits[$date]);
    }
}
