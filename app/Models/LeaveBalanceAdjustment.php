<?php

namespace App\Models;

use App\Enums\LeaveBalanceAdjustmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceAdjustment extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_balance_id',
        'leave_type_code',
        'year',
        'action_type',
        'previous_entitlement',
        'new_entitlement',
        'previous_balance',
        'adjustment_days',
        'new_balance',
        'reason',
        'effective_date',
        'leave_application_id',
        'updated_by',
        'authorized_by_name',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'action_type' => LeaveBalanceAdjustmentType::class,
            'previous_entitlement' => 'float',
            'new_entitlement' => 'float',
            'previous_balance' => 'float',
            'adjustment_days' => 'float',
            'new_balance' => 'float',
            'effective_date' => 'date',
            'recorded_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function leaveTypeLabel(): string
    {
        $standard = \App\Enums\LeaveType::tryFrom($this->leave_type_code);
        if ($standard) {
            return $standard->label();
        }

        return \App\Enums\SpecialLeaveType::tryFrom($this->leave_type_code)?->label() ?? ucfirst(str_replace('_', ' ', $this->leave_type_code));
    }

    public function adjustmentLabel(): string
    {
        $days = (float) $this->adjustment_days;
        if ($days > 0) {
            return '+'.rtrim(rtrim(number_format($days, 1), '0'), '.').' days';
        }
        if ($days < 0) {
            return rtrim(rtrim(number_format($days, 1), '0'), '.').' days';
        }

        return '0 days';
    }
}
