<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Services\DirectoryCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => AccountStatus::class,
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function leaveWorkflow(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LeaveApprovalWorkflow::class);
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    public function scopeActive($query)
    {
        return $query->where('status', AccountStatus::Active);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    protected static function booted(): void
    {
        static::saved(fn () => DirectoryCatalog::flush());
        static::deleted(fn () => DirectoryCatalog::flush());
    }
}
