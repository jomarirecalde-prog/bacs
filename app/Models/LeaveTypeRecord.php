<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveTypeRecord extends Model
{
    protected $table = 'leave_types';

    protected $fillable = [
        'code',
        'name',
        'entitlement_days',
        'category',
        'is_special',
        'counts_calendar_days',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'entitlement_days' => 'integer',
            'is_special' => 'boolean',
            'counts_calendar_days' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function entitlementFor(string $code, int $fallback = 0): int
    {
        $record = static::query()->where('code', $code)->first();

        return $record ? (int) $record->entitlement_days : $fallback;
    }
}
