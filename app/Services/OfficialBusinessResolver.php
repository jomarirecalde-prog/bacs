<?php

namespace App\Services;

use App\Enums\OfficialBusinessStatus;
use App\Models\OfficialBusinessRequest;
use App\Support\ManilaTime;
use Illuminate\Support\Collection;

class OfficialBusinessResolver
{
    /** @var array<string, array<int, OfficialBusinessRequest|false>> */
    private array $hits = [];

    /** @var array<string, true> */
    private array $loaded = [];

    public function approvedOn(int $employeeId, string $date): ?OfficialBusinessRequest
    {
        $date = ManilaTime::parse($date)->toDateString();

        if (! $this->has($employeeId, $date)) {
            $start = ManilaTime::parse($date)->startOfMonth()->toDateString();
            $end = ManilaTime::parse($date)->endOfMonth()->toDateString();
            $this->loadForEmployee($employeeId, $start, $end);
        }

        $hit = $this->hits[$date][$employeeId] ?? false;

        return $hit instanceof OfficialBusinessRequest ? $hit : null;
    }

    /**
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
        $statuses = [OfficialBusinessStatus::Approved, OfficialBusinessStatus::Completed];

        OfficialBusinessRequest::query()
            ->whereIn('status', $statuses)
            ->whereDate('date_covered_start', '<=', $date)
            ->whereDate('date_covered_end', '>=', $date)
            ->where(function ($query) use ($ids) {
                $query->whereIn('employee_id', $ids->all())
                    ->orWhereHas('participants', fn ($p) => $p->whereIn('employee_id', $ids->all()));
            })
            ->with(['participants:id,official_business_request_id,employee_id'])
            ->get()
            ->each(function (OfficialBusinessRequest $request) use ($date, $ids) {
                $affected = $request->participants
                    ->pluck('employee_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->push((int) $request->employee_id)
                    ->unique()
                    ->intersect($ids)
                    ->values();

                foreach ($affected as $employeeId) {
                    $this->hits[$date][$employeeId] = $request;
                }
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

        $statuses = [OfficialBusinessStatus::Approved, OfficialBusinessStatus::Completed];

        $requests = OfficialBusinessRequest::query()
            ->whereIn('status', $statuses)
            ->where('date_covered_start', '<=', $to)
            ->where('date_covered_end', '>=', $from)
            ->where(function ($q) use ($employeeId) {
                $q->where('employee_id', $employeeId)
                    ->orWhereHas('participants', fn ($p) => $p->where('employee_id', $employeeId));
            })
            ->get();

        $cursor = ManilaTime::parse($from);
        $end = ManilaTime::parse($to);

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $match = $requests->first(
                fn (OfficialBusinessRequest $request) => $request->date_covered_start->toDateString() <= $date
                    && $request->date_covered_end->toDateString() >= $date
            );
            $this->hits[$date][$employeeId] = $match ?: false;
            $cursor->addDay();
        }
    }

    public function flush(): void
    {
        $this->hits = [];
        $this->loaded = [];
    }

    private function has(int $employeeId, string $date): bool
    {
        return array_key_exists($date, $this->hits)
            && array_key_exists($employeeId, $this->hits[$date]);
    }
}
