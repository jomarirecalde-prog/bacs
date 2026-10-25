<?php

namespace App\Models;

use App\Enums\PayrollWorkdayClassification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollOvertimeCompensationRate extends Model
{
    protected $fillable = [
        'condition_code',
        'label',
        'workday_classification',
        'is_rest_day',
        'is_night_shift',
        'is_overtime',
        'multiplier',
        'effective_from',
        'effective_to',
        'is_active',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'workday_classification' => PayrollWorkdayClassification::class,
            'is_rest_day' => 'boolean',
            'is_night_shift' => 'boolean',
            'is_overtime' => 'boolean',
            'multiplier' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function percentage(): float
    {
        return round((float) $this->multiplier * 100, 4);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectiveOn($query, string $date)
    {
        return $query
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
    }
}
