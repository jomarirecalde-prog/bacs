<?php

namespace App\Models;

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AttendancePunchType;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Models\Concerns\HasCentralApprovalTimeline;
use App\Support\ManilaTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectionRequest extends Model
{
    use HasCentralApprovalTimeline;
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
        'central_approval_config_id',
        'central_approval_config_version',
        'current_approval_stage',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'original_value' => 'datetime',
            'requested_value' => 'datetime',
            'status' => AttendanceCorrectionStatus::class,
            'current_approval_stage' => LeaveApprovalStage::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function getCurrentStageAttribute(): ?LeaveApprovalStage
    {
        return $this->current_approval_stage;
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

    public function approvalAssignments()
    {
        return $this->hasMany(AttendanceCorrectionApprovalAssignment::class);
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

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            AttendanceCorrectionStatus::Pending->value,
            AttendanceCorrectionStatus::PendingEndorsement->value,
            AttendanceCorrectionStatus::PendingFinalApproval->value,
        ]);
    }

    /** In-flight correction requests (all workflow stages). */
    public function scopePending($query)
    {
        return $query->open();
    }

    public function assignmentsFor(LeaveApprovalStage $stage)
    {
        return $this->approvalAssignments->where('stage', $stage);
    }

    public function stageDecision(LeaveApprovalStage $stage): ?string
    {
        $rows = $this->assignmentsFor($stage);
        if ($rows->isEmpty()) {
            return null;
        }

        if ($rows->contains(fn (AttendanceCorrectionApprovalAssignment $row) => $row->isDenied())) {
            return LeaveDecision::Denied->value;
        }

        if ($rows->every(fn (AttendanceCorrectionApprovalAssignment $row) => $row->isApproved())) {
            return LeaveDecision::Approved->value;
        }

        if ($rows->contains(fn (AttendanceCorrectionApprovalAssignment $row) => $row->isApproved())) {
            return 'mixed';
        }

        return null;
    }

    public function routedThroughCentralApproval(): bool
    {
        return filled($this->central_approval_config_id)
            || $this->approvalAssignments()->exists();
    }

    public function allowsAdminDirectReview(): bool
    {
        if (! $this->status?->isOpen()) {
            return false;
        }

        if ($this->status !== AttendanceCorrectionStatus::Pending) {
            return false;
        }

        return ! $this->routedThroughCentralApproval();
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
