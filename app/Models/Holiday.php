<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = [
        'name',
        'holiday_date',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
        ];
    }

    public static function isHoliday(string $date): bool
    {
        return static::query()->whereDate('holiday_date', $date)->exists();
    }

    public static function forDate(string $date): ?self
    {
        return static::query()->whereDate('holiday_date', $date)->first();
    }
}
