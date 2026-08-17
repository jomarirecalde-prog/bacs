<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function scopeActive($query)
    {
        return $query->where('status', AccountStatus::Active);
    }

    public static function defaultSchedule(): ?self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->active()->first();
    }
}
