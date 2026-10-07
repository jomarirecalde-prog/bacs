<?php

namespace App\Models;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\LeaveParallelRule;
use App\Enums\OfficialTimeStatus;
use App\Models\Concerns\HasCentralApprovalTimeline;
use App\Support\ManilaTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficialTimeRequest extends Model
{
    use HasCentralApprovalTimeline;

    protected $fillable = [
        'request_no',
        'employee_id',
        'department_id',
        'designation_id',
        'official_time_type_id',
        'date',
        'time_from',
        'time_to',
        'am_time_in',
        'am_time_out',
        'pm_time_in',
        'pm_time_out',
        'duration_minutes',
        'purpose',
        'activity',
        'location',
        'remarks',
        'attachment_path',
        'attachment_name',
        'status',
        'current_stage',
        'parallel_rule',
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
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => OfficialTimeStatus::class,
            'current_stage' => LeaveApprovalStage::class,
            'parallel_rule' => LeaveParallelRule::class,
            'duration_minutes' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'returned_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function officialTimeType(): BelongsTo
    {
        return $this->belongsTo(OfficialTimeType::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OfficialTimeApprovalAssignment::class)->orderBy('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(OfficialTimeApprovalAction::class)->orderBy('acted_at')->orderBy('id');
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

    public function punchTimeValue(string $field): string
    {
        $value = $this->{$field} ?? null;
        if ($value === null || $value === '') {
            return '';
        }

        return substr((string) $value, 0, 5);
    }

    public function timeRangeLabel(): string
    {
        $parts = [];

        if ($this->am_time_in && $this->am_time_out) {
            $parts[] = $this->formatClockRange($this->am_time_in, $this->am_time_out);
        }

        if ($this->pm_time_in && $this->pm_time_out) {
            $parts[] = $this->formatClockRange($this->pm_time_in, $this->pm_time_out);
        }

        if ($parts !== []) {
            return implode('; ', $parts);
        }

        if ($this->time_from && $this->time_to) {
            return $this->formatClockRange($this->time_from, $this->time_to);
        }

        return '—';
    }

    private function formatClockRange(string $from, string $to): string
    {
        $fromLabel = ManilaTime::parse('1970-01-01 '.$from)->format('g:i A');
        $toLabel = ManilaTime::parse('1970-01-01 '.$to)->format('g:i A');

        return "{$fromLabel} – {$toLabel}";
    }

    public function durationLabel(): string
    {
        $hours = intdiv($this->duration_minutes, 60);
        $mins = $this->duration_minutes % 60;

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
        return in_array($this->status, [OfficialTimeStatus::Draft, OfficialTimeStatus::Returned], true);
    }

    public function canBeCancelledByEmployee(): bool
    {
        if ($this->status === OfficialTimeStatus::Draft || $this->status === OfficialTimeStatus::Returned) {
            return true;
        }

        if (! $this->status?->isOpen()) {
            return false;
        }

        return $this->assignments()->whereNotNull('acted_at')->doesntExist();
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

        if ($rows->every(fn (OfficialTimeApprovalAssignment $row) => $row->status === 'skipped')) {
            return 'skipped';
        }

        if ($rows->contains(fn (OfficialTimeApprovalAssignment $row) => $row->isDenied())) {
            if ($stage->isParallel() && $this->parallel_rule !== LeaveParallelRule::All) {
                if ($rows->contains(fn (OfficialTimeApprovalAssignment $row) => $row->isApproved())) {
                    return 'mixed';
                }
            }

            return LeaveDecision::Denied->value;
        }

        if ($rows->filter(fn (OfficialTimeApprovalAssignment $row) => $row->status !== 'skipped')->every->isApproved()) {
            return LeaveDecision::Approved->value;
        }

        if ($rows->contains(fn (OfficialTimeApprovalAssignment $row) => $row->isApproved())) {
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
            $q->where('request_no', 'like', $like)
                ->orWhere('purpose', 'like', $like)
                ->orWhereHas('employee', function ($employee) use ($like) {
                    $employee->where('full_name', 'like', $like)
                        ->orWhere('employee_number', 'like', $like);
                });
        });
    }
}
