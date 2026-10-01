<?php

namespace App\Services;

use App\Enums\TravelOrderStatus;
use App\Models\TravelOrder;
use App\Support\ManilaTime;
use Illuminate\Support\Collection;

/**
 * Request-scoped lookup for approved travel orders affecting DTR and attendance.
 */
class TravelOrderResolver
{
    /** @var array<string, array<int, TravelOrder|false>> date => employee_id => order|miss */
    private array $hits = [];

    /** @var array<string, true> */
    private array $loaded = [];

    public function approvedOn(int $employeeId, string $date): ?TravelOrder
    {
        $date = ManilaTime::parse($date)->toDateString();

        if (! $this->has($employeeId, $date)) {
            $start = ManilaTime::parse($date)->startOfMonth()->toDateString();
            $end = ManilaTime::parse($date)->endOfMonth()->toDateString();
            $this->loadForEmployee($employeeId, $start, $end);
        }

        $hit = $this->hits[$date][$employeeId] ?? false;

        return $hit instanceof TravelOrder ? $hit : null;
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

        TravelOrder::query()
            ->where('status', TravelOrderStatus::Approved)
            ->where('date_start', '<=', $date)
            ->where('date_end', '>=', $date)
            ->whereHas('personnel', fn ($q) => $q->whereIn('employee_id', $ids->all()))
            ->with(['personnel:id,travel_order_id,employee_id'])
            ->get()
            ->each(function (TravelOrder $order) use ($date) {
                foreach ($order->personnel as $person) {
                    $this->hits[$date][$person->employee_id] = $order;
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

        $orders = TravelOrder::query()
            ->where('status', TravelOrderStatus::Approved)
            ->where('date_start', '<=', $to)
            ->where('date_end', '>=', $from)
            ->whereHas('personnel', fn ($q) => $q->where('employee_id', $employeeId))
            ->get();

        $cursor = ManilaTime::parse($from);
        $end = ManilaTime::parse($to);

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $match = $orders->first(
                fn (TravelOrder $order) => $order->date_start->toDateString() <= $date
                    && $order->date_end->toDateString() >= $date
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
