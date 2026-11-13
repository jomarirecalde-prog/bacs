<?php

namespace App\Models;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\LeaveParallelRule;
use App\Enums\EndorsementMode;
use App\Enums\OfficialBusinessStatus;
use App\Enums\TravelTransportation;
use App\Services\OfficialBusinessResolver;
use App\Support\ManilaTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasCentralApprovalTimeline;

class OfficialBusinessRequest extends Model
{
    use HasCentralApprovalTimeline;

    protected $fillable = [
        'reference_number',
        'employee_id',
        'department_id',
        'workflow_id',
        'workflow_version',
        'central_approval_config_id',
        'central_approval_config_version',
        'date_filed',
        'official_station',
        'purpose',
        'purpose_particular',
        'destination_from',
        'destination_to',
        'date_covered_start',
        'date_covered_end',
        'departure_time',
        'transportation_mode',
        'transportation_other',
        'vehicle_id',
        'vehicle_name',
        'plate_number',
        'accommodation_type',
        'accommodation_particular',
        'trip_ticket_id',
        'status',
        'current_stage',
        'parallel_rule',
        'endorsement_mode',
        'secondary_endorsement_mode',
        'secondary_parallel_rule',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'date_filed' => 'date',
            'date_covered_start' => 'date',
            'date_covered_end' => 'date',
            'status' => OfficialBusinessStatus::class,
            'current_stage' => LeaveApprovalStage::class,
            'parallel_rule' => LeaveParallelRule::class,
            'endorsement_mode' => EndorsementMode::class,
            'secondary_endorsement_mode' => EndorsementMode::class,
            'secondary_parallel_rule' => LeaveParallelRule::class,
            'transportation_mode' => TravelTransportation::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function participants(): HasMany
    {
        return $this->hasMany(OfficialBusinessParticipant::class)->orderBy('sort_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OfficialBusinessApprovalAssignment::class)->orderBy('id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(OfficialBusinessApprovalAction::class)->orderBy('acted_at')->orderBy('id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dateRangeLabel(): string
    {
        $from = optional($this->date_covered_start)->format('M j, Y');
        $to = optional($this->date_covered_end)->format('M j, Y');

        return $from === $to ? (string) $from : "{$from} – {$to}";
    }

    public function destinationLabel(): string
    {
        $from = trim((string) $this->destination_from);
        $to = trim((string) $this->destination_to);

        if ($from !== '' && $to !== '') {
            return $from.' → '.$to;
        }

        return $from ?: ($to ?: '—');
    }

    public function transportationLabel(): string
    {
        if ($this->transportation_mode === TravelTransportation::Other && filled($this->transportation_other)) {
            return $this->transportation_other;
        }

        return $this->transportation_mode?->label() ?? '—';
    }

    public function canBeEditedByEmployee(): bool
    {
        return $this->status === OfficialBusinessStatus::Draft
            || ($this->status === OfficialBusinessStatus::PendingSupervisor
                && $this->assignments()->whereNotNull('acted_at')->doesntExist());
    }

    public function canBeCancelledByEmployee(): bool
    {
        return $this->status?->isOpen() ?? false;
    }

    public function canDownloadPdf(): bool
    {
        return in_array($this->status, [OfficialBusinessStatus::Approved, OfficialBusinessStatus::Completed], true);
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

        if ($rows->every(fn (OfficialBusinessApprovalAssignment $row) => $row->status === 'skipped')) {
            return 'skipped';
        }

        if ($rows->contains(fn (OfficialBusinessApprovalAssignment $row) => $row->isDenied())) {
            if ($stage->isParallel() && $this->parallel_rule !== LeaveParallelRule::All) {
                if ($rows->contains(fn (OfficialBusinessApprovalAssignment $row) => $row->isApproved())) {
                    return 'mixed';
                }
            }

            return LeaveDecision::Denied->value;
        }

        if ($rows->filter(fn (OfficialBusinessApprovalAssignment $row) => $row->status !== 'skipped')->every->isApproved()) {
            return LeaveDecision::Approved->value;
        }

        if ($rows->contains(fn (OfficialBusinessApprovalAssignment $row) => $row->isApproved())) {
            return 'mixed';
        }

        return null;
    }

    public static function approvedOn(int $employeeId, string $date): ?self
    {
        return app(OfficialBusinessResolver::class)->approvedOn($employeeId, $date);
    }

    public function scopeOwnedByEmployee($query, Employee $employee): Builder
    {
        return $query->where('employee_id', $employee->id);
    }

    public function scopeParticipating($query, Employee $employee): Builder
    {
        return $query->whereHas('participants', fn ($q) => $q->where('employee_id', $employee->id));
    }

    public function scopeSearch($query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like) {
            $q->where('reference_number', 'like', $like)
                ->orWhere('purpose', 'like', $like)
                ->orWhere('destination_from', 'like', $like)
                ->orWhere('destination_to', 'like', $like)
                ->orWhereHas('employee', function ($employee) use ($like) {
                    $employee->where('full_name', 'like', $like)
                        ->orWhere('employee_number', 'like', $like);
                });
        });
    }

    public function refreshCompletedStatus(): void
    {
        if ($this->status !== OfficialBusinessStatus::Approved) {
            return;
        }

        $end = $this->date_covered_end?->toDateString();
        if ($end && $end < ManilaTime::todayDate()) {
            $this->update(['status' => OfficialBusinessStatus::Completed]);
        }
    }
}
