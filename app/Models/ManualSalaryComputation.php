<?php

namespace App\Models;

use App\Enums\ManualSalaryComputationMode;
use App\Enums\ManualSalaryComputationStatus;
use App\Enums\ManualSalaryMonthlySubtype;
use App\Enums\ManualSalaryPayableIntent;
use App\Enums\SalaryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualSalaryComputation extends Model
{
    protected $fillable = [
        'employee_id',
        'payroll_period_id',
        'computation_mode',
        'monthly_subtype',
        'payable_intent',
        'coverage_start',
        'coverage_end',
        'payroll_year',
        'payroll_month',
        'status',
        'salary_type',
        'basic_salary',
        'monthly_salary',
        'semi_monthly_salary',
        'daily_rate',
        'hourly_rate',
        'working_hours_per_day',
        'attendance_inputs',
        'system_snapshot',
        'basic_pay',
        'overtime_pay',
        'holiday_pay',
        'premium_pay',
        'de_minimis',
        'other_earnings',
        'gross_wage',
        'gross_compensation',
        'attendance_deductions',
        'statutory_deductions',
        'other_deductions',
        'total_deductions',
        'net_pay',
        'remarks',
        'computation_warnings',
        'prepared_by',
        'prepared_at',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'finalized_by',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'computation_mode' => ManualSalaryComputationMode::class,
            'monthly_subtype' => ManualSalaryMonthlySubtype::class,
            'payable_intent' => ManualSalaryPayableIntent::class,
            'status' => ManualSalaryComputationStatus::class,
            'coverage_start' => 'date',
            'coverage_end' => 'date',
            'salary_type' => SalaryType::class,
            'basic_salary' => 'decimal:2',
            'monthly_salary' => 'decimal:2',
            'semi_monthly_salary' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'hourly_rate' => 'decimal:4',
            'working_hours_per_day' => 'integer',
            'attendance_inputs' => 'array',
            'system_snapshot' => 'array',
            'basic_pay' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'holiday_pay' => 'decimal:2',
            'premium_pay' => 'decimal:2',
            'de_minimis' => 'decimal:2',
            'other_earnings' => 'decimal:2',
            'gross_wage' => 'decimal:2',
            'gross_compensation' => 'decimal:2',
            'attendance_deductions' => 'decimal:2',
            'statutory_deductions' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'computation_warnings' => 'array',
            'prepared_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ManualSalaryComputationLine::class, 'computation_id')->orderBy('sort_order');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(ManualSalaryComputationOverride::class, 'computation_id')->latest();
    }

    public function preparedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function isPayable(): bool
    {
        if ($this->monthly_subtype === ManualSalaryMonthlySubtype::Summary) {
            return false;
        }

        return true;
    }
}
