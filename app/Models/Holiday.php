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
        // Compare the DATE column directly so the date index can be used
        // (whereDate() wraps the column in DATE() and defeats indexes).
        return static::query()->where('holiday_date', $date)->exists();
    }

    public static function forDate(string $date): ?self
    {
        return static::query()->where('holiday_date', $date)->first();
    }
}
