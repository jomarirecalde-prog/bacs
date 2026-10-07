<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAdjustment extends Model
{
    protected $fillable = [
        'employee_id',
        'source_payroll_period_id',
        'target_payroll_period_id',
        'adjustment_type',
        'original_amount',
        'corrected_amount',
        'adjustment_amount',
        'direction',
        'reason',
        'reference_type',
        'reference_id',
        'approved_by',
        'created_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'corrected_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function sourcePeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'source_payroll_period_id');
    }

    public function targetPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'target_payroll_period_id');
    }
}
