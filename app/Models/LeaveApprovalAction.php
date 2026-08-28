<?php

namespace App\Models;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\LeaveStatus;
use App\Support\ManilaTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApprovalAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'leave_application_id',
        'assignment_id',
        'user_id',
        'stage',
        'action',
        'decision',
        'previous_status',
        'new_status',
        'reason',
        'signature',
        'ip_address',
        'user_agent',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'stage' => LeaveApprovalStage::class,
            'decision' => LeaveDecision::class,
            'previous_status' => LeaveStatus::class,
            'new_status' => LeaveStatus::class,
            'acted_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class, 'leave_application_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LeaveApprovalAssignment::class, 'assignment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actedAtLabel(): string
    {
        return (string) ManilaTime::formatDateTime($this->acted_at, 'M j, Y g:i A');
    }
}
