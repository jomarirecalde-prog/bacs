<?php

namespace App\Models;

use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflowConfiguration extends Model
{
    protected $fillable = [
        'transaction_type',
        'endorsement_enabled',
        'final_approval_enabled',
        'version',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => ApprovalTransactionType::class,
            'endorsement_enabled' => 'boolean',
            'final_approval_enabled' => 'boolean',
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowAssignee::class, 'workflow_configuration_id')->orderBy('sequence_order');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowConfigurationHistory::class, 'workflow_configuration_id')->latest();
    }

    public function activeEndorsers()
    {
        return $this->assignees()
            ->where('approval_type', ApprovalAssigneeType::Endorser->value)
            ->where('is_active', true)
            ->with('employee.user');
    }

    public function activeFinalApprover()
    {
        return $this->assignees()
            ->where('approval_type', ApprovalAssigneeType::FinalApprover->value)
            ->where('is_active', true)
            ->with('employee.user')
            ->first();
    }

    public static function forType(ApprovalTransactionType $type): self
    {
        return static::query()
            ->where('transaction_type', $type->value)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
