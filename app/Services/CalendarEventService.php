<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\CalendarEventType;
use App\Enums\EventAudience;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Events\CalendarEventChanged;
use App\Models\CalendarEvent;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CalendarEventService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
        private readonly HolidayResolver $holidays,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, int>  $departmentIds
     * @param  array<int, int>  $employeeIds
     */
    public function create(array $payload, array $departmentIds, array $employeeIds, User $actor): CalendarEvent
    {
        $event = DB::transaction(function () use ($payload, $departmentIds, $employeeIds, $actor) {
            $event = CalendarEvent::query()->create($payload + [
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncAudience($event, $departmentIds, $employeeIds);

            return $event;
        });

        $this->holidays->flush();

        $this->audit->log(
            $actor,
            'create',
            'calendar',
            $event->id,
            "Created calendar event \"{$event->title}\" ({$event->event_type->label()}) on {$event->dateLabel()}."
        );

        $this->announce($event, 'created', $actor);

        return $event->fresh(['departments', 'employees']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, int>  $departmentIds
     * @param  array<int, int>  $employeeIds
     */
    public function update(CalendarEvent $event, array $payload, array $departmentIds, array $employeeIds, User $actor): CalendarEvent
    {
        $wasNonWorking = $event->isNonWorking();
        $previousStatus = $event->status;
        $wasVisible = $event->status->isVisibleToEmployees();

        DB::transaction(function () use ($event, $payload, $departmentIds, $employeeIds, $actor) {
            $event->update($payload + ['updated_by' => $actor->id]);
            $this->syncAudience($event, $departmentIds, $employeeIds);
        });

        $event->refresh();
        $this->holidays->flush();

        $note = "Updated calendar event \"{$event->title}\".";
        if ($wasNonWorking !== $event->isNonWorking()) {
            $note .= $event->isNonWorking()
                ? ' Date range is now treated as non-working for attendance.'
                : ' Date range no longer affects attendance.';
        }

        $this->audit->log($actor, 'update', 'calendar', $event->id, $note);

        $action = $this->updateAction($previousStatus, $event->status, $wasVisible);
        if ($action) {
            $this->announce($event, $action, $actor);
        }

        return $event->fresh(['departments', 'employees']);
    }

    public function delete(CalendarEvent $event, User $actor): void
    {
        $affected = $event->affectsAttendance();
        $title = $event->title;
        $range = $event->dateLabel();
        $wasVisible = $event->status->isVisibleToEmployees();

        if ($wasVisible) {
            $this->announce($event, 'deleted', $actor);
        }

        $event->delete();
        $this->holidays->flush();

        $description = "Deleted calendar event \"{$title}\" ({$range}).";
        if ($affected) {
            $description .= ' This event previously marked those dates as non-working; attendance for that range will recalculate as regular working days.';
        }

        $this->audit->log($actor, 'delete', 'calendar', $event->id, $description);
    }

    /**
     * @param  array<int, int>  $departmentIds
     * @param  array<int, int>  $employeeIds
     */
    private function syncAudience(CalendarEvent $event, array $departmentIds, array $employeeIds): void
    {
        $event->departments()->sync(
            $event->audience_type === EventAudience::Departments ? $departmentIds : []
        );

        $event->employees()->sync(
            $event->audience_type === EventAudience::Employees ? $employeeIds : []
        );
    }

    /**
     * Resolves the concrete user accounts an event is addressed to.
     *
     * @return Collection<int, User>
     */
    public function audienceUsers(CalendarEvent $event): Collection
    {
        $event->loadMissing(['departments:id', 'employees:id']);

        $employees = match ($event->audience_type) {
            EventAudience::All => Employee::query()->active()->with('user')->get(),
            EventAudience::Departments => Employee::query()->active()->with('user')
                ->whereIn('department_id', $event->departments->pluck('id'))
                ->get(),
            EventAudience::Employees => Employee::query()->active()->with('user')
                ->whereIn('id', $event->employees->pluck('id'))
                ->get(),
        };

        return $employees
            ->map(fn (Employee $employee) => $employee->user)
            ->filter()
            ->unique('id')
            ->values();
    }

    private function updateAction(EventStatus $from, EventStatus $to, bool $wasVisible): ?string
    {
        if ($to === EventStatus::Draft) {
            return $wasVisible ? 'cancelled' : null;
        }

        if ($from === EventStatus::Draft && $to->isVisibleToEmployees()) {
            return 'created';
        }

        if ($to === EventStatus::Cancelled && $from !== EventStatus::Cancelled) {
            return 'cancelled';
        }

        if ($to->isVisibleToEmployees()) {
            return 'updated';
        }

        return null;
    }

    /**
     * Persist in-app notifications for the event audience and fan a live
     * calendar refresh to those users plus signed-in management.
     */
    private function announce(CalendarEvent $event, string $action, User $actor): void
    {
        if ($event->status === EventStatus::Draft && in_array($action, ['created', 'updated'], true)) {
            $this->broadcastCalendarChange($event, $action, collect(), $actor);

            return;
        }

        $event->loadMissing(['departments', 'employees']);

        $audience = $this->audienceUsers($event);
        $copy = $this->notificationCopy($event, $action);

        if ($copy) {
            try {
                $audience
                    ->reject(fn (User $user) => $user->id === $actor->id)
                    ->each(function (User $user) use ($event, $action, $copy) {
                        $this->notifications->notify(
                            $user,
                            $copy['title'],
                            $copy['message'],
                            $copy['type'],
                            $this->calendarLink($user, $event),
                            $event->id,
                            $action,
                            toast: $event->notify_audience || $action !== 'created',
                        );
                    });

                $event->forceFill(['notified_at' => now()])->saveQuietly();
            } catch (\Throwable) {
                // Event mutation succeeded; missed alerts remain available after the next login.
            }
        }

        $this->broadcastCalendarChange($event, $action, $audience, $actor);
    }

    /**
     * @param  Collection<int, User>  $audience
     */
    private function broadcastCalendarChange(CalendarEvent $event, string $action, Collection $audience, User $actor): void
    {
        $viewerIds = $audience->pluck('id')
            ->merge($this->managementUserIds())
            ->push($actor->id)
            ->unique()
            ->filter()
            ->values();

        try {
            $viewerIds->each(fn (int $userId) => broadcast(new CalendarEventChanged($userId, $event, $action)));
        } catch (\Throwable) {
            // Calendar writes must succeed even if Reverb is not running.
        }
    }

    /**
     * @return Collection<int, int>
     */
    private function managementUserIds(): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::Admin, UserRole::Supervisor])
            ->where('status', AccountStatus::Active)
            ->pluck('id');
    }

    private function calendarLink(User $user, CalendarEvent $event): string
    {
        $date = $event->start_date->toDateString();

        return $user->isManagement()
            ? route('admin.calendar.index', ['view' => 'day', 'date' => $date])
            : route('employee.calendar', ['view' => 'day', 'date' => $date]);
    }

    /**
     * @return array{title: string, message: string, type: string}|null
     */
    private function notificationCopy(CalendarEvent $event, string $action): ?array
    {
        $title = $event->title;
        $when = $event->dateLabel();
        $time = $event->timeLabel();
        $location = $event->location ? ' · '.$event->location : '';

        return match ($action) {
            'cancelled', 'deleted' => [
                'title' => '❌ Calendar Event Cancelled: The scheduled '.$event->event_type->shortLabel().' has been cancelled.',
                'message' => "{$title} ({$when}) is no longer on the company calendar.",
                'type' => 'warning',
            ],
            'updated' => [
                'title' => '✏️ Calendar Event Updated: The schedule for '.$title.' has changed.',
                'message' => trim("{$when} · {$time}{$location}"),
                'type' => 'info',
            ],
            'created' => $this->createdCopy($event, $title, $when, $time, $location),
            default => null,
        };
    }

    /**
     * @return array{title: string, message: string, type: string}
     */
    private function createdCopy(CalendarEvent $event, string $title, string $when, string $time, string $location): array
    {
        return match ($event->event_type) {
            CalendarEventType::Holiday, CalendarEventType::SpecialNonWorking => [
                'title' => '📅 New Holiday: '.$title.' has been added on '.$when.'.'.($event->isNonWorking() ? ' No attendance is required.' : ''),
                'message' => $event->attendance_effect?->label() ?? $event->event_type->label(),
                'type' => 'success',
            ],
            CalendarEventType::Meeting => [
                'title' => '📢 New Company Meeting: '.$title.' is scheduled for '.$when.($event->is_all_day ? '.' : ' at '.$time.'.'),
                'message' => trim($event->location ?: 'See the company calendar for details.'),
                'type' => 'info',
            ],
            CalendarEventType::Announcement => [
                'title' => '🔔 New Announcement: Please check the company calendar for an important announcement.',
                'message' => $title.($when ? ' · '.$when : ''),
                'type' => 'warning',
            ],
            default => [
                'title' => '📅 '.$event->event_type->label().': '.$title,
                'message' => trim("{$when} · {$time}{$location}"),
                'type' => 'info',
            ],
        };
    }
}
