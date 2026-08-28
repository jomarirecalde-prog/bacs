<?php

namespace App\Models;

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AttendancePunchType;
use App\Support\ManilaTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectionRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'attendance_id',
        'attendance_date',
        'punch_type',
        'original_value',
        'requested_value',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'original_value' => 'datetime',
            'requested_value' => 'datetime',
            'status' => AttendanceCorrectionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function punchType(): ?AttendancePunchType
    {
        return AttendancePunchType::tryFrom($this->punch_type);
    }

    public function punchLabel(): string
    {
        return $this->punchType()?->label() ?? ucwords(str_replace('_', ' ', $this->punch_type));
    }

    public function scopeOwnedBy($query, Employee $employee)
    {
        return $query->where('employee_id', $employee->id);
    }

    public function scopePending($query)
    {
        return $query->where('status', AttendanceCorrectionStatus::Pending->value);
    }

    public function scopeForDate($query, string $date)
    {
        $end = ManilaTime::parse($date)->addDay()->toDateString();

        return $query->where('attendance_date', '>=', $date)
            ->where('attendance_date', '<', $end);
    }

    public function formattedOriginal(): string
    {
        return $this->original_value
            ? ManilaTime::formatTime($this->original_value) ?? '—'
            : '—';
    }

    public function formattedRequested(): string
    {
        return ManilaTime::formatTime($this->requested_value) ?? '—';
    }
}
