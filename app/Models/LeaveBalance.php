<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveBalance extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_type_code',
        'year',
        'entitled_days',
        'used_days',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitled_days' => 'float',
            'used_days' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(LeaveBalanceAdjustment::class);
    }

    public function remaining(): float
    {
        return max(0, (float) $this->entitled_days - (float) $this->used_days);
    }
}
