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
        $match = $departmentId
            ? static::query()->where('department_id', $departmentId)->where('is_active', true)->first()
            : null;

        return $match ?: static::query()->where('is_default', true)->firstOrFail();
    }
}
