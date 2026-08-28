<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;

/**
 * Mirrors the permission split already used across the app: management
 * (Super Admin + Boss/Management) may read, while only Super Admin may write.
 * Employees never reach these abilities — they use the read-only employee
 * calendar, which is filtered by CalendarEvent::scopeVisibleToEmployee.
 */
class CalendarEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManagement();
    }

    public function view(User $user, CalendarEvent $event): bool
    {
        return $user->isManagement();
    }

    public function create(User $user): bool
    {
        return $user->canManageCalendar();
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return $user->canManageCalendar();
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return $user->canManageCalendar();
    }

    /**
     * Configuring how an event affects attendance and absence calculations is
     * deliberately narrower than ordinary event editing.
     */
    public function configureAttendanceEffect(User $user): bool
    {
        return $user->canManageCalendar();
    }
}
