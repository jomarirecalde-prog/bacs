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

    public function departmentSettings(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowDepartmentSetting::class, 'workflow_configuration_id');
    }

    public function departmentSettingFor(int $departmentId): ?ApprovalWorkflowDepartmentSetting
    {
        return $this->departmentSettings->firstWhere('department_id', $departmentId)
            ?? $this->departmentSettings()->where('department_id', $departmentId)->first();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowConfigurationHistory::class, 'workflow_configuration_id')->latest();
    }

    public function activeEndorsers(?int $departmentId = null)
    {
        $query = $this->assignees()
            ->where('approval_type', ApprovalAssigneeType::Endorser->value)
            ->where('is_active', true)
            ->with('employee.user');

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        return $query;
    }

    public function activeFinalApprover(?int $departmentId = null)
    {
        $query = $this->assignees()
            ->where('approval_type', ApprovalAssigneeType::FinalApprover->value)
            ->where('is_active', true)
            ->with('employee.user');

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        return $query->first();
    }

    public static function forType(ApprovalTransactionType $type): self
    {
        return static::query()
            ->where('transaction_type', $type->value)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
