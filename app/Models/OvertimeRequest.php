<?php

namespace App\Models;

use App\Enums\OvertimeRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimeRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'attendance_id',
        'attendance_date',
        'recorded_minutes',
        'approved_minutes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'central_approval_config_id',
        'central_approval_config_version',
        'current_approval_stage',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'recorded_minutes' => 'integer',
            'approved_minutes' => 'integer',
            'status' => OvertimeRequestStatus::class,
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvalAssignments(): HasMany
    {
        return $this->hasMany(OvertimeApprovalAssignment::class);
    }

    public function approvedHours(): float
    {
        if ($this->status !== OvertimeRequestStatus::Approved) {
            return 0.0;
        }

        return round($this->approved_minutes / 60, 2);
    }
}
