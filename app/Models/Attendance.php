<?php

namespace App\Models;

use App\Enums\AttendancePunchType;
use App\Enums\AttendanceStatus;
use App\Support\ManilaTime;
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
        'am_time_in',
        'am_time_out',
        'pm_time_in',
        'pm_time_out',
        'overtime_in',
        'punch_stations',
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
            'am_time_in' => 'datetime',
            'am_time_out' => 'datetime',
            'pm_time_in' => 'datetime',
            'pm_time_out' => 'datetime',
            'overtime_in' => 'datetime',
            'punch_stations' => 'array',
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

    public function scopeOnDate($query, string $date)
    {
        $end = ManilaTime::parse($date)->addDay()->toDateString();

        return $query->where('attendance_date', '>=', $date)
            ->where('attendance_date', '<', $end);
    }

    public function scopeBetweenDates($query, string $from, string $to)
    {
        $end = ManilaTime::parse($to)->addDay()->toDateString();

        return $query->where('attendance_date', '>=', $from)
            ->where('attendance_date', '<', $end);
    }

    public function punchValue(AttendancePunchType $type): mixed
    {
        return $this->{$type->column()};
    }

    public function hasPunch(AttendancePunchType $type): bool
    {
        return $this->punchValue($type) !== null;
    }

    public function isRegularComplete(): bool
    {
        foreach (AttendancePunchType::regularSequence() as $type) {
            if (! $this->hasPunch($type)) {
                return false;
            }
        }

        return true;
    }

    public function syncLegacyFields(): void
    {
        $this->time_in = $this->am_time_in;
        $this->time_out = $this->pm_time_out ?? $this->overtime_in;

        $stations = $this->punch_stations ?? [];
        $amInStation = $stations[AttendancePunchType::AmTimeIn->value] ?? null;
        $pmOutStation = $stations[AttendancePunchType::PmTimeOut->value] ?? null;

        if ($amInStation) {
            $this->time_in_station_id = $amInStation['station_id'] ?? null;
            $this->time_in_station_name = $amInStation['station_name'] ?? null;
            $this->time_in_station_location = $amInStation['station_location'] ?? null;
        }

        if ($pmOutStation) {
            $this->time_out_station_id = $pmOutStation['station_id'] ?? null;
            $this->time_out_station_name = $pmOutStation['station_name'] ?? null;
            $this->time_out_station_location = $pmOutStation['station_location'] ?? null;
        }
    }

    /** @return array<string, mixed> */
    public function punchPayload(): array
    {
        return [
            'am_time_in' => ManilaTime::formatTime($this->am_time_in),
            'am_time_out' => ManilaTime::formatTime($this->am_time_out),
            'pm_time_in' => ManilaTime::formatTime($this->pm_time_in),
            'pm_time_out' => ManilaTime::formatTime($this->pm_time_out),
            'overtime' => ManilaTime::formatTime($this->overtime_in),
            'attendance_date' => $this->attendance_date?->toDateString(),
            'day' => $this->attendance_date?->format('l'),
            'total_hours' => $this->totalHoursLabel(),
            'regular_hours' => self::minutesToLabel($this->total_minutes),
            'overtime_hours' => $this->overtimeHoursLabel(),
            'attendance_status' => $this->displayStatus(),
            'status_value' => $this->status?->value,
            'late_minutes' => $this->late_minutes,
            'undertime_minutes' => $this->undertime_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'time_in' => ManilaTime::formatTime($this->time_in),
            'time_out' => ManilaTime::formatTime($this->time_out),
        ];
    }

    public function totalHoursLabel(): string
    {
        return self::minutesToLabel($this->total_minutes);
    }

    public function overtimeHoursLabel(): string
    {
        return self::minutesToLabel($this->overtime_minutes);
    }

    public static function minutesToLabel(?int $minutes): string
    {
        $minutes ??= 0;
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }

    public function hasTimeIn(): bool
    {
        return $this->am_time_in !== null || $this->time_in !== null;
    }

    public function hasTimeOut(): bool
    {
        return $this->pm_time_out !== null || $this->time_out !== null;
    }

    public function isClockedIn(): bool
    {
        return $this->hasTimeIn() && ! $this->isRegularComplete();
    }

    public function displayStatus(): string
    {
        if ($this->isClockedIn() && $this->attendance_date?->isToday()) {
            return 'Currently Working';
        }

        return $this->status?->label() ?? '—';
    }
}
