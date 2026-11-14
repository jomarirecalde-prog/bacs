<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class WorkSchedule extends Model
{
    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'grace_period_minutes',
        'break_start',
        'break_end',
        'required_minutes',
        'work_days',
        'is_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'grace_period_minutes' => 'integer',
            'required_minutes' => 'integer',
            'work_days' => 'array',
            'is_default' => 'boolean',
            'status' => AccountStatus::class,
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function workDays(): array
    {
        return $this->work_days ?: [1, 2, 3, 4, 5];
    }

    public function isWorkDay(int $isoDayOfWeek): bool
    {
        return in_array($isoDayOfWeek, $this->workDays(), true);
    }

    public function effectiveRequiredMinutes(): int
    {
        if ((int) $this->required_minutes > 0) {
            return (int) $this->required_minutes;
        }

        $start = Carbon::parse((string) $this->start_time);
        $end = Carbon::parse((string) $this->end_time);
        $span = (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60);

        $break = 0;
        if ($this->break_start && $this->break_end) {
            $break = (int) floor(
                (Carbon::parse((string) $this->break_end)->getTimestamp()
                    - Carbon::parse((string) $this->break_start)->getTimestamp()) / 60
            );
        }

        return max(0, $span - $break);
    }

    public function scopeActive($query)
    {
        return $query->where('status', AccountStatus::Active);
    }

    public static function defaultSchedule(): ?self
    {
        return Cache::remember('work_schedule:default', 600, function () {
            return static::query()->where('is_default', true)->first()
                ?? static::query()->active()->first();
        });
    }

    public static function flushDefaultCache(): void
    {
        Cache::forget('work_schedule:default');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushDefaultCache());
        static::deleted(fn () => static::flushDefaultCache());
    }
}
