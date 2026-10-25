<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualSalaryComputationLine extends Model
{
    protected $fillable = [
        'computation_id',
        'direction',
        'category',
        'code',
        'label',
        'amount',
        'is_manual',
        'is_system',
        'sort_order',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_manual' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
            'meta' => 'array',
        ];
    }

    public function computation(): BelongsTo
    {
        return $this->belongsTo(ManualSalaryComputation::class, 'computation_id');
    }
}
