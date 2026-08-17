<?php

namespace App\Models;

use App\Enums\StationActivityResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StationActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'attendance_station_id',
        'employee_id',
        'action',
        'result',
        'failure_reason',
        'message',
        'ip_address',
        'device_identifier_hash',
        'scanned_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => StationActivityResult::class,
            'scanned_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(AttendanceStation::class, 'attendance_station_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
