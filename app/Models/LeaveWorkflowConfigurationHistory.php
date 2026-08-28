<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveWorkflowConfigurationHistory extends Model
{
    protected $fillable = [
        'workflow_id',
        'department_id',
        'action',
        'previous_configuration',
        'new_configuration',
        'summary',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_configuration' => 'array',
            'new_configuration' => 'array',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(LeaveApprovalWorkflow::class, 'workflow_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
