<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficialTimeType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'requires_attachment',
        'requires_location',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_attachment' => 'boolean',
            'requires_location' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(OfficialTimeRequest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
