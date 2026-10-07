<?php

namespace App\Models;

use App\Enums\PayrollPeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $fillable = [
        'period_name',
        'start_date',
        'end_date',
        'payroll_date',
        'status',
        'period_key',
        'created_by',
        'approved_by',
        'approved_at',
        'finalized_by',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'payroll_date' => 'date',
            'status' => PayrollPeriodStatus::class,
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function attendanceSummaries(): HasMany
    {
        return $this->hasMany(PayrollAttendanceSummary::class);
    }

    public function payrollEmployees(): HasMany
    {
        return $this->hasMany(PayrollEmployee::class);
    }

    public function isLocked(): bool
    {
        return $this->status?->isLocked() ?? false;
    }
}
