<?php

namespace App\Models;

use App\Enums\LeaveApprovalStage;
use App\Enums\OfficialBusinessStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialBusinessApprovalAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'official_business_request_id',
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
            'previous_status' => OfficialBusinessStatus::class,
            'new_status' => OfficialBusinessStatus::class,
            'acted_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(OfficialBusinessRequest::class, 'official_business_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
