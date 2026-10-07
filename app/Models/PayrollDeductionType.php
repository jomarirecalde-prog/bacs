<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollDeductionType extends Model
{
    protected $fillable = ['code', 'name', 'sort_order', 'is_statutory', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_statutory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
