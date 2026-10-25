<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualSalaryComputationOverride extends Model
{
    protected $fillable = [
        'computation_id',
        'field_key',
        'original_value',
        'manual_value',
        'reason',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'original_value' => 'decimal:4',
            'manual_value' => 'decimal:4',
        ];
    }

    public function computation(): BelongsTo
    {
        return $this->belongsTo(ManualSalaryComputation::class, 'computation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
