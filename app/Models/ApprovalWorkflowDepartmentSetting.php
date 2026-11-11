<?php

namespace App\Models;

use App\Enums\EndorsementMode;
use App\Enums\LeaveParallelRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowDepartmentSetting extends Model
{
    protected $fillable = [
        'workflow_configuration_id',
        'department_id',
        'endorsement_mode',
        'endorsement_parallel_rule',
        'secondary_endorsement_mode',
        'secondary_endorsement_parallel_rule',
    ];

    protected function casts(): array
    {
        return [
            'endorsement_mode' => EndorsementMode::class,
            'endorsement_parallel_rule' => LeaveParallelRule::class,
            'secondary_endorsement_mode' => EndorsementMode::class,
            'secondary_endorsement_parallel_rule' => LeaveParallelRule::class,
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
}
