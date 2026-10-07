<?php

namespace App\Models;

use App\Enums\SalaryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    protected $fillable = [
        'designation_code',
        'designation_name',
        'department_id',
        'description',
        'default_pay_type',
        'default_basic_salary',
        'default_semi_monthly_salary',
        'default_daily_rate',
        'default_hourly_rate',
        'default_working_hours_per_day',
        'default_working_days_per_period',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_pay_type' => SalaryType::class,
            'default_basic_salary' => 'decimal:2',
            'default_semi_monthly_salary' => 'decimal:2',
            'default_daily_rate' => 'decimal:2',
            'default_hourly_rate' => 'decimal:4',
            'default_working_hours_per_day' => 'integer',
            'default_working_days_per_period' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like) {
            $q->where('designation_name', 'like', $like)
                ->orWhere('designation_code', 'like', $like);
        });
    }
}
