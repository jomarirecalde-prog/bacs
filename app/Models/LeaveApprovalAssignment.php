<?php

namespace App\Models;

use App\Enums\LeaveApprovalStage;
use App\Models\Concerns\InteractsWithApprovalAssignmentStatus;
use App\Support\ManilaTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApprovalAssignment extends Model
{
    use InteractsWithApprovalAssignmentStatus;
    protected $fillable = [
        'leave_application_id',
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

    public function application(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function decisionLabel(): string
    {
        return match ($this->status) {
            'approved' => 'Endorsed',
            'denied' => 'Rejected',
            'skipped' => 'Skipped',
            'waiting' => 'Waiting turn',
            default => 'Pending',
        };
    }

    public function actedAtLabel(): ?string
    {
        return $this->acted_at ? ManilaTime::formatDateTime($this->acted_at, 'M j, Y g:i A') : null;
    }
}
