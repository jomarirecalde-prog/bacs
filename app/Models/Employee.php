<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_number',
        'first_name',
        'middle_name',
        'last_name',
        'full_name',
        'email',
        'contact_number',
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
            if (blank($employee->full_name)) {
                $middle = $employee->middle_name ? ' '.$employee->middle_name : '';
                $employee->full_name = trim($employee->last_name.', '.$employee->first_name.$middle);
            }
        });
    }

    public function schedule(): WorkSchedule
    {
        return $this->workSchedule ?: WorkSchedule::defaultSchedule() ?? new WorkSchedule([
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
        if ($this->photo) {
            return asset('storage/'.$this->photo);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->fullName()).'&background=0f766e&color=fff';
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
