<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSssBracket extends Model
{
    protected $fillable = [
        'effective_from',
        'compensation_min',
        'compensation_max',
        'monthly_salary_credit',
        'employee_share_monthly',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'compensation_min' => 'decimal:2',
            'compensation_max' => 'decimal:2',
            'monthly_salary_credit' => 'decimal:2',
            'employee_share_monthly' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
