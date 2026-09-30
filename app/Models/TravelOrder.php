<?php

namespace App\Models;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\LeaveParallelRule;
use App\Enums\TravelOrderStatus;
use App\Enums\TravelTransportation;
use App\Support\ManilaTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelOrder extends Model
{
    protected $fillable = [
        'travel_order_number',
        'requester_id',
        'department_id',
        'workflow_id',
        'workflow_version',
        'central_approval_config_id',
        'central_approval_config_version',
        'official_station',
        'number_of_bh',
        'destination',
        'date_start',
        'date_end',
        'purpose',
        'equipment',
        'project_name',
        'client_company',
        'transportation',
        'transportation_other',
        'vehicle_type',
        'plate_number',
        'remarks',
        'status',
        'current_stage',
        'parallel_rule',
        'date_requested',
        'submitted_at',
        'approved_at',
        'finalized_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'submitted_by',
    ];

    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end' => 'date',
            'status' => TravelOrderStatus::class,
            'current_stage' => LeaveApprovalStage::class,
            'parallel_rule' => LeaveParallelRule::class,
            'transportation' => TravelTransportation::class,
            'date_requested' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(LeaveApprovalWorkflow::class, 'workflow_id');
    }

    public function personnel(): HasMany
    {
        return $this->hasMany(TravelOrderPersonnel::class)->orderBy('id');
    }

    public function travelers(): HasMany
    {
        return $this->personnel();
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(TravelOrderDestination::class)->orderBy('sort_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TravelOrderApprovalAssignment::class)->orderBy('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(TravelOrderApprovalAction::class)->orderBy('acted_at')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TravelOrderAttachment::class);
    }

    public function modificationLogs(): HasMany
    {
        return $this->hasMany(TravelOrderModificationLog::class)->latest();
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

    public function dateRangeLabel(): string
    {
        $from = optional($this->date_start)->format('M j, Y');
        $to = optional($this->date_end)->format('M j, Y');

        return $from === $to ? (string) $from : "{$from} – {$to}";
    }

    public function requestedLabel(): ?string
    {
        return $this->date_requested
            ? ManilaTime::formatDateTime($this->date_requested, 'M j, Y g:i A')
            : null;
    }

    public function transportationLabel(): string
    {
        if ($this->transportation === TravelTransportation::Other && filled($this->transportation_other)) {
            return $this->transportation_other;
        }

        return $this->transportation?->label() ?? '—';
    }

    public function canBeEditedByRequester(): bool
    {
        return $this->status === TravelOrderStatus::Draft
            || ($this->status === TravelOrderStatus::PendingSupervisor
                && $this->assignments()->whereNotNull('acted_at')->doesntExist());
    }

    public function canBeCancelledByRequester(): bool
    {
        return $this->status?->isOpen() ?? false;
    }

    public function canDownloadPdf(): bool
    {
        return $this->status === TravelOrderStatus::Approved;
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

        if ($rows->every(fn (TravelOrderApprovalAssignment $row) => $row->status === 'skipped')) {
            return 'skipped';
        }

        if ($rows->contains(fn (TravelOrderApprovalAssignment $row) => $row->isDenied())) {
            if ($stage->isParallel() && $this->parallel_rule !== LeaveParallelRule::All) {
                if ($rows->contains(fn (TravelOrderApprovalAssignment $row) => $row->isApproved())) {
                    return 'mixed';
                }
            }

            return LeaveDecision::Denied->value;
        }

        if ($rows->filter(fn (TravelOrderApprovalAssignment $row) => $row->status !== 'skipped')->every->isApproved()) {
            return LeaveDecision::Approved->value;
        }

        if ($rows->contains(fn (TravelOrderApprovalAssignment $row) => $row->isApproved())) {
            return 'mixed';
        }

        return null;
    }

    public function scopeOwnedByRequester($query, Employee $employee): Builder
    {
        return $query->where('requester_id', $employee->id);
    }

    public function scopeParticipating($query, Employee $employee): Builder
    {
        return $query->whereHas('personnel', fn ($q) => $q->where('employee_id', $employee->id));
    }

    public function scopeSearch($query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like) {
            $q->where('travel_order_number', 'like', $like)
                ->orWhere('purpose', 'like', $like)
                ->orWhere('destination', 'like', $like)
                ->orWhereHas('requester', function ($employee) use ($like) {
                    $employee->where('full_name', 'like', $like)
                        ->orWhere('employee_number', 'like', $like);
                })
                ->orWhereHas('personnel.employee', function ($employee) use ($like) {
                    $employee->where('full_name', 'like', $like)
                        ->orWhere('employee_number', 'like', $like);
                });
        });
    }
}
