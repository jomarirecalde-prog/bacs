<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowConfigurationHistory extends Model
{
    protected $fillable = [
        'workflow_configuration_id',
        'version',
        'snapshot',
        'summary',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'version' => 'integer',
        ];
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowConfiguration::class, 'workflow_configuration_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
