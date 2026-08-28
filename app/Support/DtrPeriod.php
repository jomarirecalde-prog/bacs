<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

final class DtrPeriod
{
    public function __construct(
        public readonly string $start,
        public readonly string $end,
        public readonly string $key,
        public readonly string $label,
        public readonly string $cutoffLabel,
        public readonly string $type,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $period = trim((string) $request->string('period'));
        if ($period !== '') {
            return self::parse($period);
        }

        if ($request->filled('month') && $request->filled('year')) {
            return self::month($request->integer('year'), $request->integer('month'));
        }

        return self::current();
    }

    public static function current(?Carbon $now = null): self
    {
        return self::cutoffContaining($now ?? ManilaTime::today());
    }

    public static function parse(string $key): self
    {
        if (preg_match('/^m:(\d{4})-(\d{2})$/', $key, $m)) {
            return self::month((int) $m[1], (int) $m[2]);
        }

        if (preg_match('/^c:(\d{4}-\d{2}-\d{2})_(\d{4}-\d{2}-\d{2})$/', $key, $m)) {
            return self::cutoff($m[1], $m[2]);
        }

        return self::current();
    }

    public static function month(int $year, int $month): self
    {
        $start = Carbon::create($year, $month, 1, 0, 0, 0, ManilaTime::TIMEZONE)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $label = $start->format('F Y');

        return new self(
            start: $start->toDateString(),
            end: $end->toDateString(),
            key: sprintf('m:%04d-%02d', $year, $month),
            label: $label,
            cutoffLabel: $label,
            type: 'month',
        );
    }

    public static function cutoff(string $start, string $end): self
    {
        $from = ManilaTime::parse($start)->startOfDay();
        $to = ManilaTime::parse($end)->startOfDay();

        return new self(
            start: $from->toDateString(),
            end: $to->toDateString(),
            key: 'c:'.$from->toDateString().'_'.$to->toDateString(),
            label: self::rangeLabel($from, $to),
            cutoffLabel: self::compactRangeLabel($from, $to),
            type: 'cutoff',
        );
    }

    public static function cutoffContaining(Carbon $date): self
    {
        $day = (int) $date->day;

        if ($day >= 11 && $day <= 25) {
            return self::cutoff(
                $date->copy()->day(11)->toDateString(),
                $date->copy()->day(25)->toDateString()
            );
        }

        if ($day >= 26) {
            return self::cutoff(
                $date->copy()->day(26)->toDateString(),
                $date->copy()->addMonthNoOverflow()->day(10)->toDateString()
            );
        }

        return self::cutoff(
            $date->copy()->subMonthNoOverflow()->day(26)->toDateString(),
            $date->copy()->day(10)->toDateString()
        );
    }

    /**
     * @return list<self>
     */
    public static function options(?Carbon $now = null): array
    {
        $now ??= ManilaTime::today();
        $options = [];
        $seen = [];

        $cursor = self::cutoffContaining($now);
        for ($i = 0; $i < 16; $i++) {
            if (! isset($seen[$cursor->key])) {
                $options[] = $cursor;
                $seen[$cursor->key] = true;
            }
            $cursor = self::cutoffContaining(ManilaTime::parse($cursor->start)->subDay());
        }

        $month = $now->copy()->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $period = self::month((int) $month->year, (int) $month->month);
            if (! isset($seen[$period->key])) {
                $options[] = $period;
                $seen[$period->key] = true;
            }
            $month->subMonthNoOverflow();
        }

        return $options;
    }

    public function query(): array
    {
        return ['period' => $this->key];
    }

    private static function rangeLabel(Carbon $from, Carbon $to): string
    {
        if ($from->isSameMonth($to)) {
            return $from->format('F j').' – '.$to->format('j, Y');
        }

        return $from->format('F j, Y').' – '.$to->format('F j, Y');
    }

    private static function compactRangeLabel(Carbon $from, Carbon $to): string
    {
        if ($from->isSameMonth($to)) {
            return $from->format('M j').'–'.$to->format('j, Y');
        }

        return $from->format('M j').' – '.$to->format('M j, Y');
    }
}
