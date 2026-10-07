<?php

namespace App\Models;

use App\Enums\PayrollComputationStatus;
use App\Enums\SalaryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollEmployee extends Model
{
    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'employee_number',
        'employee_name',
        'department_id',
        'department_name',
        'designation_id',
        'designation_name',
        'salary_type',
        'basic_salary',
        'monthly_salary',
        'semi_monthly_salary',
        'daily_rate',
        'hourly_rate',
        'working_hours_per_day',
        'basic_pay',
        'absence_deduction',
        'late_deduction',
        'undertime_deduction',
        'computed_total_basic_pay',
        'total_basic_pay',
        'total_basic_pay_manually_set',
        'overtime_pay',
        'holiday_pay',
        'premium_pay',
        'gross_wage',
        'de_minimis',
        'other_earnings',
        'gross_compensation',
        'total_deductions',
        'net_pay',
        'computation_status',
        'computation_warnings',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'salary_type' => SalaryType::class,
            'basic_salary' => 'decimal:2',
            'monthly_salary' => 'decimal:2',
            'semi_monthly_salary' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'hourly_rate' => 'decimal:4',
            'working_hours_per_day' => 'integer',
            'basic_pay' => 'decimal:2',
            'absence_deduction' => 'decimal:2',
            'late_deduction' => 'decimal:2',
            'undertime_deduction' => 'decimal:2',
            'computed_total_basic_pay' => 'decimal:2',
            'total_basic_pay' => 'decimal:2',
            'total_basic_pay_manually_set' => 'boolean',
            'overtime_pay' => 'decimal:2',
            'holiday_pay' => 'decimal:2',
            'premium_pay' => 'decimal:2',
            'gross_wage' => 'decimal:2',
            'de_minimis' => 'decimal:2',
            'other_earnings' => 'decimal:2',
            'gross_compensation' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'computation_status' => PayrollComputationStatus::class,
            'computation_warnings' => 'array',
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

    public function earnings(): HasMany
    {
        return $this->hasMany(PayrollEarning::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayrollDeduction::class);
    }
}
