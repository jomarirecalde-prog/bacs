<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Events\NotificationReceived;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    /**
     * Persist a notification and push it to the recipient's private channel.
     *
     * Duplicate alerts for the same user + event + action within a short
     * window are skipped so a retried request or double-submit cannot spam
     * every open tab.
     */
    public function notify(
        User $user,
        string $title,
        string $message,
        string $type = 'info',
        ?string $link = null,
        ?int $calendarEventId = null,
        ?string $action = null,
        bool $toast = true,
        ?int $leaveApplicationId = null,
    ): ?AppNotification {
        $contextId = $calendarEventId ?: $leaveApplicationId;
        $contextPrefix = $calendarEventId ? 'calendar' : ($leaveApplicationId ? 'leave' : 'generic');

        if ($action && $this->isDuplicate($user->id, $contextPrefix, $contextId, $action, $title.$message)) {
            return null;
        }

        $notification = AppNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
            'calendar_event_id' => $calendarEventId,
            'leave_application_id' => $leaveApplicationId,
            'action' => $action,
        ]);

        $this->broadcast($notification, $user, $toast);

        return $notification;
    }

    public function notifyAdmins(string $title, string $message, string $type = 'info', ?string $link = null): void
    {
        User::query()
            ->select(['id'])
            ->whereIn('role', [UserRole::Admin, UserRole::Supervisor])
            ->where('status', 'active')
            ->each(fn (User $user) => $this->notify($user, $title, $message, $type, $link));
    }

    public function unreadCount(User $user): int
    {
        return $user->appNotifications()->unread()->count();
    }

    public function latest(User $user, int $limit = 8)
    {
        return $user->appNotifications()->latest()->limit($limit)->get();
    }

    public function markRead(AppNotification $notification): void
    {
        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }
    }

    public function markAllRead(User $user): void
    {
        $user->appNotifications()->unread()->update(['read_at' => now()]);
    }

    private function broadcast(AppNotification $notification, User $user, bool $toast = true): void
    {
        try {
            broadcast(new NotificationReceived($notification, $this->unreadCount($user), $toast));
        } catch (\Throwable) {
            // Persistence succeeded; a down WebSocket server must not fail the request.
        }
    }

    private function isDuplicate(int $userId, string $prefix, ?int $contextId, string $action, string $fingerprint): bool
    {
        $key = $prefix.'-notify:'.$userId.':'.($contextId ?? 0).':'.$action.':'.md5($fingerprint);

        return ! Cache::add($key, 1, 20);
    }
}
