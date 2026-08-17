<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'time_in',
        'time_in_station_id',
        'time_in_station_name',
        'time_in_station_location',
        'time_out',
        'time_out_station_id',
        'time_out_station_name',
        'time_out_station_location',
        'total_minutes',
        'late_minutes',
        'undertime_minutes',
        'overtime_minutes',
        'status',
        'remarks',
        'is_manual',
        'is_edited',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'time_in' => 'datetime',
            'time_out' => 'datetime',
            'total_minutes' => 'integer',
            'late_minutes' => 'integer',
            'undertime_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'status' => AttendanceStatus::class,
            'is_manual' => 'boolean',
            'is_edited' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function timeInStation(): BelongsTo
    {
        return $this->belongsTo(AttendanceStation::class, 'time_in_station_id');
    }

    public function timeOutStation(): BelongsTo
    {
        return $this->belongsTo(AttendanceStation::class, 'time_out_station_id');
    }

    public function edits(): HasMany
    {
        return $this->hasMany(AttendanceEdit::class);
    }

    public function totalHoursLabel(): string
    {
        return self::minutesToLabel($this->total_minutes);
    }

    public function overtimeHoursLabel(): string
    {
        return self::minutesToLabel($this->overtime_minutes);
    }

    public static function minutesToLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }

    public function hasTimeIn(): bool
    {
        return $this->time_in !== null;
    }

    public function hasTimeOut(): bool
    {
        return $this->time_out !== null;
    }

    public function isClockedIn(): bool
    {
        return $this->hasTimeIn() && ! $this->hasTimeOut();
    }

    public function displayStatus(): string
    {
        if ($this->isClockedIn() && $this->attendance_date?->isToday()) {
            return 'Currently Working';
        }

        return $this->status?->label() ?? '—';
    }
}
