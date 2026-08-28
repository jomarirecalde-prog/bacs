<?php

namespace App\Models;

use App\Enums\LeaveApprovalStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApprovalWorkflowApprover extends Model
{
    protected $fillable = [
        'workflow_id',
        'stage',
        'user_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'stage' => LeaveApprovalStage::class,
            'sort_order' => 'integer',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(LeaveApprovalWorkflow::class, 'workflow_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
