<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollTaxBracket extends Model
{
    protected $fillable = [
        'effective_from',
        'compensation_min',
        'compensation_max',
        'base_tax',
        'rate_on_excess',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'compensation_min' => 'decimal:2',
            'compensation_max' => 'decimal:2',
            'base_tax' => 'decimal:2',
            'rate_on_excess' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }
}
