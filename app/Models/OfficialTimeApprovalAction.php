<?php

namespace App\Models;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\OfficialTimeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialTimeApprovalAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'official_time_request_id',
        'assignment_id',
        'user_id',
        'stage',
        'action',
        'decision',
        'previous_status',
        'new_status',
        'reason',
        'ip_address',
        'user_agent',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'stage' => LeaveApprovalStage::class,
            'decision' => LeaveDecision::class,
            'previous_status' => OfficialTimeStatus::class,
            'new_status' => OfficialTimeStatus::class,
            'acted_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(OfficialTimeRequest::class, 'official_time_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(OfficialTimeApprovalAssignment::class, 'assignment_id');
    }
}
