<?php

namespace App\Models;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveParallelRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveApprovalWorkflow extends Model
{
    protected $fillable = [
        'department_id',
        'name',
        'parallel_rule',
        'is_default',
        'is_active',
        'version',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'parallel_rule' => LeaveParallelRule::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function configurationHistories(): HasMany
    {
        return $this->hasMany(LeaveWorkflowConfigurationHistory::class, 'workflow_id')->latest();
    }

    public function approvers(): HasMany
    {
        return $this->hasMany(LeaveApprovalWorkflowApprover::class, 'workflow_id')->orderBy('stage')->orderBy('sort_order');
    }

    public function approversFor(LeaveApprovalStage $stage): HasMany
    {
        return $this->approvers()->where('stage', $stage->value);
    }

    public static function forDepartment(?int $departmentId): self
    {
        if ($departmentId) {
            $match = static::query()
                ->where('department_id', $departmentId)
                ->where('is_active', true)
                ->first();

            if ($match) {
                return $match;
            }
        }

        return static::query()->where('is_default', true)->firstOrFail();
    }

    public function isDepartmentSpecific(): bool
    {
        return $this->department_id !== null && ! $this->is_default;
    }
}
