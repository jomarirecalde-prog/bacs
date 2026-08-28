<?php

namespace App\Services;

use App\Enums\LeaveType;
use App\Enums\SpecialLeaveType;
use App\Models\Employee;
use App\Models\Holiday;
use App\Support\ManilaTime;

class LeaveDayCalculator
{
    public function days(
        Employee $employee,
        string $start,
        string $end,
        LeaveType $type,
        ?SpecialLeaveType $special = null
    ): float {
        $from = ManilaTime::parse($start)->startOfDay();
        $to = ManilaTime::parse($end)->startOfDay();

        if ($to->lt($from)) {
            return 0;
        }

        $calendar = $type->countsCalendarDays() || ($special?->countsCalendarDays() ?? false);

        if ($calendar) {
            return (float) ($from->diffInDays($to) + 1);
        }

        $holidays = Holiday::query()
            ->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('holiday_date')
            ->map(fn ($date) => ManilaTime::parse($date)->toDateString())
            ->all();

        $schedule = $employee->schedule();
        $count = 0;
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $date = $cursor->toDateString();
            $isWorkDay = $schedule->isWorkDay((int) $cursor->isoWeekday());
            $isHoliday = in_array($date, $holidays, true);

            if ($isWorkDay && ! $isHoliday) {
                $count++;
            }

            $cursor->addDay();
        }

        return (float) $count;
    }

    /** @return list<string> */
    public function dates(string $start, string $end): array
    {
        $from = ManilaTime::parse($start)->startOfDay();
        $to = ManilaTime::parse($end)->startOfDay();
        $dates = [];

        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
            $dates[] = $cursor->toDateString();
        }

        return $dates;
    }

    public function assertValidRange(string $start, string $end): void
    {
        $from = ManilaTime::parse($start);
        $to = ManilaTime::parse($end);

        if ($to->lt($from)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'end_date' => 'The ending date cannot be earlier than the starting date.',
            ]);
        }
    }
}
