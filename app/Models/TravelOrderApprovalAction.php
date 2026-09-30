<?php

namespace App\Models;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\TravelOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelOrderApprovalAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'travel_order_id',
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
            'previous_status' => TravelOrderStatus::class,
            'new_status' => TravelOrderStatus::class,
            'acted_at' => 'datetime',
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
}
