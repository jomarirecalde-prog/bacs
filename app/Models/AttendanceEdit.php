<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEdit extends Model
{
    protected $fillable = [
        'attendance_id',
        'original_time_in',
        'original_time_out',
        'original_status',
        'new_time_in',
        'new_time_out',
        'new_status',
        'field_changes',
        'reason',
        'modified_by',
        'modified_at',
    ];

    protected function casts(): array
    {
        return [
            'original_time_in' => 'datetime',
            'original_time_out' => 'datetime',
            'new_time_in' => 'datetime',
            'new_time_out' => 'datetime',
            'field_changes' => 'array',
            'modified_at' => 'datetime',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }
}
