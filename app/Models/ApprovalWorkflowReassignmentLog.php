<?php

namespace App\Models;

use App\Enums\ApprovalTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalWorkflowReassignmentLog extends Model
{
    protected $fillable = [
        'workflow_configuration_id',
        'transaction_type',
        'department_id',
        'assignable_type',
        'assignable_id',
        'assignment_id',
        'stage',
        'previous_user_id',
        'previous_employee_id',
        'new_user_id',
        'new_employee_id',
        'previous_assignment_status',
        'new_assignment_status',
        'reason',
        'initiated_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => ApprovalTransactionType::class,
        ];
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowConfiguration::class, 'workflow_configuration_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function previousUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_user_id');
    }

    public function newUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_user_id');
    }
}
