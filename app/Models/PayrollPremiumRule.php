<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollPremiumRule extends Model
{
    protected $fillable = [
        'scenario_code',
        'name',
        'pay_component',
        'multiplier',
        'holiday_type',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:4',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
