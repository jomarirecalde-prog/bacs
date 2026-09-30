<?php

namespace App\Models;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Support\ManilaTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelOrderApprovalAssignment extends Model
{
    protected $fillable = [
        'travel_order_id',
        'stage',
        'user_id',
        'employee_id',
        'approver_name',
        'approver_position',
        'approver_role',
        'status',
        'reason',
        'signature',
        'acted_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'stage' => LeaveApprovalStage::class,
            'acted_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function travelOrder(): BelongsTo
    {
        return $this->belongsTo(TravelOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === LeaveDecision::Approved->value;
    }

    public function isDenied(): bool
    {
        return $this->status === LeaveDecision::Denied->value;
    }

    public function decisionLabel(): string
    {
        return match ($this->status) {
            LeaveDecision::Approved->value => 'Endorsed',
            LeaveDecision::Denied->value => 'Rejected',
            'skipped' => 'Skipped',
            default => 'Pending',
        };
    }

    public function actedAtLabel(): ?string
    {
        return $this->acted_at ? ManilaTime::formatDateTime($this->acted_at, 'M j, Y g:i A') : null;
    }
}
