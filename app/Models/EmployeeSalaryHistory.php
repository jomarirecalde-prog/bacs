<?php

namespace App\Models;

use App\Enums\EmployeeSalaryStatus;
use App\Enums\SalaryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryHistory extends Model
{
    protected $table = 'employee_salary_history';

    protected $fillable = [
        'employee_id',
        'designation_id',
        'salary_type',
        'basic_salary',
        'gross_compensation',
        'monthly_salary',
        'semi_monthly_salary',
        'daily_rate',
        'hourly_rate',
        'working_hours_per_day',
        'working_days_basis',
        'effective_from',
        'effective_to',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'salary_type' => SalaryType::class,
            'basic_salary' => 'decimal:2',
            'gross_compensation' => 'decimal:2',
            'monthly_salary' => 'decimal:2',
            'semi_monthly_salary' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'working_hours_per_day' => 'integer',
            'working_days_basis' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'status' => EmployeeSalaryStatus::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEffectiveOn(string $date): bool
    {
        if ($this->effective_from->toDateString() > $date) {
            return false;
        }

        if ($this->effective_to && $this->effective_to->toDateString() < $date) {
            return false;
        }

        return $this->status === EmployeeSalaryStatus::Active
            || $this->status === EmployeeSalaryStatus::Superseded;
    }
}
