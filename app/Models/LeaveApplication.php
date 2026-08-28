<?php

namespace App\Models;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\LeaveParallelRule;
use App\Enums\LeavePaymentType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\SpecialLeaveType;
use App\Support\ManilaTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveApplication extends Model
{
    protected $fillable = [
        'application_number',
        'employee_id',
        'department_id',
        'workflow_id',
        'workflow_version',
        'leave_type',
        'special_leave_type',
        'start_date',
        'end_date',
        'requested_days',
        'reason',
        'employee_print_name',
        'employee_signature',
        'declaration_accepted',
        'date_filed',
        'status',
        'current_stage',
        'parallel_rule',
        'payment_type',
        'hr_sil_as_of',
        'hr_sil_balance',
        'hr_leave_taken',
        'hr_leave_balance',
        'hr_remarks',
        'attendance_conflict',
        'finalized_at',
        'finalized_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'leave_id',
        'submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'leave_type' => LeaveType::class,
            'special_leave_type' => SpecialLeaveType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'requested_days' => 'float',
            'declaration_accepted' => 'boolean',
            'date_filed' => 'datetime',
            'status' => LeaveStatus::class,
            'current_stage' => LeaveApprovalStage::class,
            'parallel_rule' => LeaveParallelRule::class,
            'payment_type' => LeavePaymentType::class,
            'hr_sil_as_of' => 'date',
            'hr_sil_balance' => 'float',
            'hr_leave_taken' => 'float',
            'hr_leave_balance' => 'float',
            'attendance_conflict' => 'boolean',
            'finalized_at' => 'datetime',
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

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(LeaveApprovalWorkflow::class, 'workflow_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeaveApprovalAssignment::class)->orderBy('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(LeaveApprovalAction::class)->orderBy('acted_at')->orderBy('id');
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(LeaveAttendanceConflict::class);
    }

    public function dtrLeave(): BelongsTo
    {
        return $this->belongsTo(Leave::class, 'leave_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function leaveTypeLabel(): string
    {
        if ($this->leave_type === LeaveType::Special && $this->special_leave_type) {
            return $this->special_leave_type->label();
        }

        return $this->leave_type?->label() ?? 'Leave';
    }

    public function balanceCode(): string
    {
        if ($this->leave_type === LeaveType::Special && $this->special_leave_type) {
            return $this->special_leave_type->value;
        }

        return $this->leave_type?->value ?? LeaveType::Vacation->value;
    }

    public function dateRangeLabel(): string
    {
        $from = optional($this->start_date)->format('M j, Y');
        $to = optional($this->end_date)->format('M j, Y');

        return $from === $to ? (string) $from : "{$from} – {$to}";
    }

    public function filedLabel(): string
    {
        return (string) ManilaTime::formatDateTime($this->date_filed, 'M j, Y g:i A');
    }

    public function canBeEdited(): bool
    {
        return $this->status === LeaveStatus::PendingSupervisor
            && $this->assignments()->whereNotNull('acted_at')->doesntExist();
    }

    public function canBeCancelled(): bool
    {
        return $this->status?->isOpen() ?? false;
    }

    public function isChecked(LeaveType $type): bool
    {
        return $this->leave_type === $type;
    }

    public function assignmentsFor(LeaveApprovalStage $stage)
    {
        return $this->assignments->where('stage', $stage);
    }

    public function pendingAssignmentsFor(?LeaveApprovalStage $stage = null)
    {
        $stage ??= $this->current_stage;

        return $this->assignments
            ->where('stage', $stage)
            ->where('status', 'pending');
    }

    public function stageDecision(LeaveApprovalStage $stage): ?string
    {
        $rows = $this->assignmentsFor($stage);
        if ($rows->isEmpty()) {
            return null;
        }

        if ($rows->every(fn (LeaveApprovalAssignment $row) => $row->status === 'skipped')) {
            return 'skipped';
        }

        if ($rows->contains(fn (LeaveApprovalAssignment $row) => $row->isDenied())) {
            if ($stage->isParallel() && $this->parallel_rule !== LeaveParallelRule::All) {
                if ($rows->contains(fn (LeaveApprovalAssignment $row) => $row->isApproved())) {
                    return 'mixed';
                }
            }

            return LeaveDecision::Denied->value;
        }

        if ($rows->filter(fn (LeaveApprovalAssignment $row) => $row->status !== 'skipped')->every->isApproved()) {
            return LeaveDecision::Approved->value;
        }

        if ($rows->contains(fn (LeaveApprovalAssignment $row) => $row->isApproved())) {
            return 'mixed';
        }

        return null;
    }

    public function scopeOwnedBy($query, Employee $employee): Builder
    {
        return $query->where('employee_id', $employee->id);
    }

    public function scopeOverlapping($query, int $employeeId, string $start, string $end, ?int $ignoreId = null): Builder
    {
        return $query->where('employee_id', $employeeId)
            ->whereIn('status', collect(LeaveStatus::cases())->filter->blocksOverlap()->map->value->all())
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId));
    }

    public function scopeSearch($query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like) {
            $q->where('application_number', 'like', $like)
                ->orWhere('reason', 'like', $like)
                ->orWhere('employee_print_name', 'like', $like)
                ->orWhereHas('employee', function ($employee) use ($like) {
                    $employee->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('full_name', 'like', $like)
                        ->orWhere('employee_number', 'like', $like);
                });
        });
    }
}
