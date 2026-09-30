<?php

namespace App\Models;

use App\Enums\ApprovalAssigneeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowAssignee extends Model
{
    protected $fillable = [
        'workflow_configuration_id',
        'employee_id',
        'approval_type',
        'sequence_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'approval_type' => ApprovalAssigneeType::class,
            'sequence_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowConfiguration::class, 'workflow_configuration_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
