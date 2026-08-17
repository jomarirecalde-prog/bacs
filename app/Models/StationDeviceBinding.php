<?php

namespace App\Models;

use App\Enums\BindingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StationDeviceBinding extends Model
{
    protected $fillable = [
        'attendance_station_id',
        'device_identifier_hash',
        'binding_token_hash',
        'bound_at',
        'last_seen_at',
        'unbound_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'bound_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'unbound_at' => 'datetime',
            'status' => BindingStatus::class,
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(AttendanceStation::class, 'attendance_station_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', BindingStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === BindingStatus::Active;
    }
}
