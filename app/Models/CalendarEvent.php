<?php

namespace App\Models;

use App\Enums\AttendanceEffect;
use App\Enums\CalendarEventType;
use App\Enums\EventAudience;
use App\Enums\EventStatus;
use App\Services\HolidayResolver;
use App\Support\ManilaTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'event_type',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'is_all_day',
        'location',
        'additional_instructions',
        'audience_type',
        'attendance_effect',
        'notify_audience',
        'notified_at',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_all_day' => 'boolean',
            'notify_audience' => 'boolean',
            'notified_at' => 'datetime',
            'event_type' => CalendarEventType::class,
            'audience_type' => EventAudience::class,
            'attendance_effect' => AttendanceEffect::class,
            'status' => EventStatus::class,
        ];
    }

    /**
     * Persist the date columns as plain `Y-m-d`.
     *
     * The default `date` cast writes `Y-m-d 00:00:00`, which MySQL truncates in
     * a DATE column but SQLite keeps verbatim — breaking the plain string
     * comparisons in scopeOverlapping(). Normalising on write keeps range
     * queries correct and index-friendly on both engines. Reads still go
     * through the `date` cast and return Carbon instances.
     */
    protected function startDate(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value ? ManilaTime::parse($value)->toDateString() : null,
        );
    }

    protected function endDate(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value ? ManilaTime::parse($value)->toDateString() : null,
        );
    }

    protected static function booted(): void
    {
        // Keep the memoised holiday map honest when events change mid-request.
        $flush = fn () => app(HolidayResolver::class)->flush();

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'calendar_event_department');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'calendar_event_employee');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /* ---------------------------------------------------------------------
     | Scopes
     |---------------------------------------------------------------------*/

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EventStatus::Published);
    }

    /**
     * Events whose [start_date, end_date] range intersects the given window.
     * Inclusive on both ends so multi-day events surface in every view they touch.
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from);
    }

    public function scopeOfType(Builder $query, mixed $type): Builder
    {
        return $type ? $query->where('event_type', $type) : $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('location', 'like', $like);
        });
    }

    /**
     * Restricts the query to events the given employee is entitled to see.
     *
     * This is the single source of truth for employee-side visibility and is
     * applied on the server for every employee-facing read.
     */
    public function scopeVisibleToEmployee(Builder $query, ?Employee $employee): Builder
    {
        return $query
            ->whereIn('status', EventStatus::employeeVisibleValues())
            ->where(function (Builder $q) use ($employee) {
                $q->where('audience_type', EventAudience::All);

                if (! $employee) {
                    return;
                }

                if ($employee->department_id) {
                    $q->orWhere(function (Builder $sub) use ($employee) {
                        $sub->where('audience_type', EventAudience::Departments)
                            ->whereHas('departments', fn (Builder $d) => $d->where('departments.id', $employee->department_id));
                    });
                }

                $q->orWhere(function (Builder $sub) use ($employee) {
                    $sub->where('audience_type', EventAudience::Employees)
                        ->whereHas('employees', fn (Builder $e) => $e->where('employees.id', $employee->id));
                });
            });
    }

    /**
     * Published holiday-style events that make a day non-working.
     */
    public function scopeNonWorking(Builder $query): Builder
    {
        return $query->published()
            ->whereIn('event_type', [
                CalendarEventType::Holiday->value,
                CalendarEventType::SpecialNonWorking->value,
            ])
            ->whereIn('attendance_effect', collect(AttendanceEffect::cases())
                ->filter(fn (AttendanceEffect $effect) => $effect->isNonWorking())
                ->map(fn (AttendanceEffect $effect) => $effect->value)
                ->all());
    }

    /* ---------------------------------------------------------------------
     | Helpers
     |---------------------------------------------------------------------*/

    public function isMultiDay(): bool
    {
        return ! $this->start_date->isSameDay($this->end_date);
    }

    /**
     * True when this event removes the obligation to time in.
     */
    public function isNonWorking(): bool
    {
        return $this->status === EventStatus::Published
            && $this->event_type->supportsAttendanceEffect()
            && $this->attendance_effect?->isNonWorking() === true;
    }

    public function affectsAttendance(): bool
    {
        return $this->event_type->supportsAttendanceEffect()
            && $this->attendance_effect !== null
            && $this->attendance_effect->isNonWorking();
    }

    public function dateLabel(): string
    {
        if (! $this->isMultiDay()) {
            return $this->start_date->format('F j, Y');
        }

        if ($this->start_date->isSameMonth($this->end_date)) {
            return $this->start_date->format('F j').' – '.$this->end_date->format('j, Y');
        }

        return $this->start_date->format('M j, Y').' – '.$this->end_date->format('M j, Y');
    }

    public function timeLabel(): string
    {
        if ($this->is_all_day || ! $this->start_time) {
            return 'All Day';
        }

        $start = ManilaTime::parse($this->start_date->toDateString().' '.$this->start_time)->format('g:i A');

        if (! $this->end_time) {
            return $start;
        }

        $end = ManilaTime::parse($this->start_date->toDateString().' '.$this->end_time)->format('g:i A');

        return $start.' – '.$end;
    }

    public function audienceLabel(): string
    {
        return match ($this->audience_type) {
            EventAudience::All => 'All Employees',
            EventAudience::Departments => $this->departments->pluck('name')->join(', ') ?: 'No department selected',
            EventAudience::Employees => $this->employees->map(fn (Employee $e) => $e->fullName())->join(', ') ?: 'No employee selected',
        };
    }

    public function audienceSummary(): string
    {
        return match ($this->audience_type) {
            EventAudience::All => 'All Employees',
            EventAudience::Departments => trans_choice(':count department|:count departments', $this->departments->count(), ['count' => $this->departments->count()]),
            EventAudience::Employees => trans_choice(':count employee|:count employees', $this->employees->count(), ['count' => $this->employees->count()]),
        };
    }
}
