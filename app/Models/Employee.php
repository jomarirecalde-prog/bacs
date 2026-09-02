<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Services\DirectoryCatalog;
use App\Services\EmployeePhotoStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    private ?WorkSchedule $resolvedSchedule = null;

    protected $fillable = [
        'user_id',
        'employee_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'full_name',
        'email',
        'contact_number',
        'address',
        'birth_date',
        'department_id',
        'position',
        'employment_status',
        'date_hired',
        'photo',
        'work_schedule_id',
    ];

    protected function casts(): array
    {
        return [
            'date_hired' => 'date',
            'birth_date' => 'date',
            'employment_status' => EmploymentStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(EmployeeQrToken::class);
    }

    public function activeQrToken(): ?EmployeeQrToken
    {
        return $this->qrTokens()->active()->latest('generated_at')->first();
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function leaveApplications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveBalanceAdjustments(): HasMany
    {
        return $this->hasMany(LeaveBalanceAdjustment::class);
    }

    public function fullName(): string
    {
        if (filled($this->full_name)) {
            return $this->full_name;
        }

        $middle = $this->middle_name ? ' '.$this->middle_name : '';

        return trim("{$this->first_name}{$middle} {$this->last_name}");
    }

    public function directoryName(): string
    {
        return $this->fullName();
    }

    protected static function booted(): void
    {
        static::saving(function (Employee $employee) {
            if ($employee->isDirty(['first_name', 'middle_name', 'last_name', 'suffix']) || blank($employee->full_name)) {
                $middle = $employee->middle_name ? ' '.$employee->middle_name : '';
                $suffix = $employee->suffix ? ' '.$employee->suffix : '';
                $employee->full_name = trim($employee->last_name.', '.$employee->first_name.$middle.$suffix);
            }
        });

        static::saved(fn () => DirectoryCatalog::flush());
        static::deleted(fn () => DirectoryCatalog::flush());
    }

    public function schedule(): WorkSchedule
    {
        if (! isset($this->resolvedSchedule)) {
            $this->resolvedSchedule = $this->workSchedule ?: WorkSchedule::defaultSchedule() ?? new WorkSchedule([
                'name' => 'Default',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'grace_period_minutes' => 10,
                'break_start' => '12:00:00',
                'break_end' => '13:00:00',
                'required_minutes' => 480,
                'work_days' => [1, 2, 3, 4, 5],
            ]);
        }

        return $this->resolvedSchedule;
    }

    public function isAccountActive(): bool
    {
        return $this->account_status === AccountStatus::Active;
    }

    protected function accountStatus(): Attribute
    {
        return Attribute::get(fn () => $this->user?->status);
    }

    public function scopeActive($query)
    {
        return $query->whereHas('user', fn ($q) => $q->where('status', AccountStatus::Active));
    }

    public function photoUrl(): string
    {
        return app(EmployeePhotoStorage::class)->url($this);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like) {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('middle_name', 'like', $like)
                ->orWhere('full_name', 'like', $like)
                ->orWhere('employee_number', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('position', 'like', $like)
                ->orWhereHas('department', fn ($d) => $d->where('name', 'like', $like));
        });
    }
}
