<?php

namespace App\Models;

use App\Enums\EndorsementMode;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\LeaveParallelRule;
use App\Enums\OvertimeAttachmentReportStatus;
use App\Models\Concerns\HasCentralApprovalTimeline;
use App\Support\ManilaTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimeAttachmentReport extends Model
{
    use HasCentralApprovalTimeline;

    protected $fillable = [
        'overtime_request_id',
        'official_time_request_id',
        'employee_id',
        'department_id',
        'report_no',
        'date_coverage_start',
        'date_coverage_end',
        'date_submitted',
        'status',
        'current_stage',
        'parallel_rule',
        'endorsement_mode',
        'secondary_endorsement_mode',
        'secondary_parallel_rule',
        'central_approval_config_id',
        'central_approval_config_version',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'finalized_by',
        'rejected_at',
        'returned_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'review_notes',
        'prepared_by',
        'approved_by',
        'total_activity_minutes',
    ];

    protected function casts(): array
    {
        return [
            'date_coverage_start' => 'date',
            'date_coverage_end' => 'date',
            'date_submitted' => 'date',
            'status' => OvertimeAttachmentReportStatus::class,
            'current_stage' => LeaveApprovalStage::class,
            'parallel_rule' => LeaveParallelRule::class,
            'endorsement_mode' => EndorsementMode::class,
            'secondary_endorsement_mode' => EndorsementMode::class,
            'secondary_parallel_rule' => LeaveParallelRule::class,
            'total_activity_minutes' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'returned_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function overtimeRequest(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class);
    }

    public function officialTimeRequest(): BelongsTo
    {
        return $this->belongsTo(OfficialTimeRequest::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OvertimeAttachmentActivity::class, 'attachment_report_id')->orderBy('sort_order')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OvertimeAttachmentApprovalAssignment::class)->orderBy('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(OvertimeAttachmentApprovalAction::class)->orderBy('acted_at')->orderBy('id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function coverageLabel(): string
    {
        $start = $this->date_coverage_start?->format('M j, Y') ?? '—';
        $end = $this->date_coverage_end?->format('M j, Y') ?? '—';

        if ($start === $end) {
            return $start;
        }

        return "{$start} – {$end}";
    }

    public function durationLabel(): string
    {
        $hours = intdiv($this->total_activity_minutes, 60);
        $mins = $this->total_activity_minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours} hr {$mins} min";
        }

        if ($hours > 0) {
            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        return "{$mins} min";
    }

    public function submittedLabel(): ?string
    {
        return $this->submitted_at
            ? ManilaTime::formatDateTime($this->submitted_at, 'M j, Y g:i A')
            : null;
    }

    public function canBeEditedByEmployee(): bool
    {
        if ($this->status === OvertimeAttachmentReportStatus::Approved) {
            return false;
        }

        return in_array($this->status, [OvertimeAttachmentReportStatus::Draft, OvertimeAttachmentReportStatus::Returned], true);
    }

    public function canBeCancelledByEmployee(): bool
    {
        return in_array($this->status, [
            OvertimeAttachmentReportStatus::Draft,
            OvertimeAttachmentReportStatus::Returned,
        ], true);
    }

    public function pendingPayrollReview(): bool
    {
        return in_array($this->status, [
            OvertimeAttachmentReportStatus::Submitted,
            OvertimeAttachmentReportStatus::UnderReview,
            OvertimeAttachmentReportStatus::Verified,
        ], true);
    }

    public function assignmentsFor(LeaveApprovalStage $stage)
    {
        return $this->assignments->where('stage', $stage);
    }

    public function stageDecision(LeaveApprovalStage $stage): ?string
    {
        $rows = $this->assignmentsFor($stage);
        if ($rows->isEmpty()) {
            return null;
        }

        if ($rows->every(fn (OvertimeAttachmentApprovalAssignment $row) => $row->status === 'skipped')) {
            return 'skipped';
        }

        if ($rows->contains(fn (OvertimeAttachmentApprovalAssignment $row) => $row->isDenied())) {
            if ($stage->isParallel() && $this->parallel_rule !== LeaveParallelRule::All) {
                if ($rows->contains(fn (OvertimeAttachmentApprovalAssignment $row) => $row->isApproved())) {
                    return 'mixed';
                }
            }

            return LeaveDecision::Denied->value;
        }

        if ($rows->filter(fn (OvertimeAttachmentApprovalAssignment $row) => $row->status !== 'skipped')->every->isApproved()) {
            return LeaveDecision::Approved->value;
        }

        if ($rows->contains(fn (OvertimeAttachmentApprovalAssignment $row) => $row->isApproved())) {
            return 'mixed';
        }

        return null;
    }

    public function scopeOwnedByEmployee($query, Employee $employee): Builder
    {
        return $query->where('employee_id', $employee->id);
    }

    public function scopeSearch($query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like) {
            $q->where('report_no', 'like', $like)
                ->orWhereHas('employee', function ($employee) use ($like) {
                    $employee->where('full_name', 'like', $like)
                        ->orWhere('employee_number', 'like', $like);
                });
        });
    }
}
