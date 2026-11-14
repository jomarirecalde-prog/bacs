<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** Modules omitted from the admin audit trail (routine UI assets, not security events). */
    public const TRAIL_HIDDEN_MODULES = [
        'EmployeePhotos',
    ];
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'record_id',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleInAdminTrail(Builder $query): Builder
    {
        return $query->whereNotIn('module', self::TRAIL_HIDDEN_MODULES);
    }

    public function actorDisplayName(): string
    {
        if ($this->user) {
            $this->user->loadMissing('employee');

            return $this->user->employee?->fullName()
                ?: ($this->user->name ?: '—');
        }

        $description = trim((string) $this->description);

        if ($description === '') {
            return 'System';
        }

        if (preg_match('/^(.+?)\s+logged\s+(?:in|out)\.\s*$/i', $description, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/\brecorded for (.+?) at /i', $description, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^Station\s+(.+?)\s+(?:logged\s+(?:in|out)|was bound)/i', $description, $matches)) {
            return 'Station '.trim($matches[1]);
        }

        if (stripos($description, 'Failed attendance station login') !== false) {
            return 'Attendance station';
        }

        return 'System';
    }
}
