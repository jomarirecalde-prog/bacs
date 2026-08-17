<?php

namespace App\Models;

use App\Enums\BindingStatus;
use App\Enums\StationDeviceStatus;
use App\Enums\StationStatus;
use App\Support\ManilaTime;
use Database\Factories\AttendanceStationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class AttendanceStation extends Authenticatable
{
    /** @use HasFactory<AttendanceStationFactory> */
    use HasFactory;

    public const ONLINE_THRESHOLD_SECONDS = 90;

    protected $fillable = [
        'station_code',
        'station_name',
        'password',
        'location',
        'description',
        'status',
        'device_status',
        'binding_nonce',
        'idle_timeout_minutes',
        'failed_login_attempts',
        'login_locked_until',
        'last_seen_at',
        'last_scan_at',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'binding_nonce',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => StationStatus::class,
            'device_status' => StationDeviceStatus::class,
            'idle_timeout_minutes' => 'integer',
            'failed_login_attempts' => 'integer',
            'login_locked_until' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_scan_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bindings(): HasMany
    {
        return $this->hasMany(StationDeviceBinding::class);
    }

    public function activeBindingRelation(): HasOne
    {
        return $this->hasOne(StationDeviceBinding::class)
            ->ofMany(['bound_at' => 'max'], fn ($q) => $q->where('status', BindingStatus::Active));
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(StationActivityLog::class);
    }

    public function activeBinding(): ?StationDeviceBinding
    {
        return $this->bindings()->active()->latest('bound_at')->first();
    }

    public function isBound(): bool
    {
        return $this->device_status === StationDeviceStatus::Bound;
    }

    public function isActive(): bool
    {
        return $this->status === StationStatus::Active;
    }

    public function isLocked(): bool
    {
        return $this->status === StationStatus::Locked;
    }

    public function isInactive(): bool
    {
        return $this->status === StationStatus::Inactive;
    }

    public function canOperate(): bool
    {
        return $this->isActive() && $this->isBound();
    }

    public function isLoginTemporarilyLocked(): bool
    {
        return $this->login_locked_until !== null && $this->login_locked_until->isFuture();
    }

    public function isOnline(): bool
    {
        if (! $this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->gte(ManilaTime::now()->subSeconds(self::ONLINE_THRESHOLD_SECONDS));
    }

    public function presenceLabel(): string
    {
        if ($this->isLocked()) {
            return 'Locked';
        }

        if ($this->isInactive()) {
            return 'Inactive';
        }

        if (! $this->isBound()) {
            return 'Unbound';
        }

        return $this->isOnline() ? 'Online' : 'Offline';
    }

    public function idleTimeoutLabel(): string
    {
        return match ((int) $this->idle_timeout_minutes) {
            30 => '30 minutes',
            60 => '1 hour',
            240 => '4 hours',
            default => 'Never',
        };
    }

    public static function idleTimeoutOptions(): array
    {
        return [
            0 => 'Never',
            30 => '30 minutes',
            60 => '1 hour',
            240 => '4 hours',
        ];
    }
}
