<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAttendanceSummary extends Model
{
    protected $table = 'payroll_attendance_summary';

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'scheduled_days',
        'worked_days',
        'paid_days',
        'absent_days',
        'regular_hours',
        'late_minutes',
        'undertime_minutes',
        'approved_ot_hours',
        'recorded_ot_hours',
        'holiday_hours',
        'rest_day_hours',
        'leave_days',
        'paid_leave_days',
        'unpaid_leave_days',
        'travel_order_days',
        'warnings',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_days' => 'integer',
            'worked_days' => 'integer',
            'paid_days' => 'decimal:2',
            'absent_days' => 'decimal:2',
            'regular_hours' => 'decimal:2',
            'late_minutes' => 'integer',
            'undertime_minutes' => 'integer',
            'approved_ot_hours' => 'decimal:2',
            'recorded_ot_hours' => 'decimal:2',
            'holiday_hours' => 'decimal:2',
            'rest_day_hours' => 'decimal:2',
            'leave_days' => 'decimal:2',
            'paid_leave_days' => 'decimal:2',
            'unpaid_leave_days' => 'decimal:2',
            'travel_order_days' => 'decimal:2',
            'warnings' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
