<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollEarningType extends Model
{
    protected $fillable = ['code', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
