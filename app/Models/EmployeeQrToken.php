<?php

namespace App\Models;

use App\Enums\QrTokenStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class EmployeeQrToken extends Model
{
    protected $fillable = [
        'employee_id',
        'token_hash',
        'token_encrypted',
        'status',
        'generated_at',
        'revoked_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QrTokenStatus::class,
            'generated_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', QrTokenStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === QrTokenStatus::Active;
    }

    public function isDisabled(): bool
    {
        return $this->status === QrTokenStatus::Disabled;
    }

    public function plainToken(): string
    {
        return Crypt::decryptString($this->token_encrypted);
    }
}
