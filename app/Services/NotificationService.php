<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AppNotification;
use App\Models\User;

class NotificationService
{
    public function notify(User $user, string $title, string $message, string $type = 'info', ?string $link = null): AppNotification
    {
        return AppNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
        ]);
    }

    public function notifyAdmins(string $title, string $message, string $type = 'info', ?string $link = null): void
    {
        User::query()
            ->whereIn('role', [UserRole::Admin, UserRole::Supervisor])
            ->where('status', 'active')
            ->get()
            ->each(fn (User $user) => $this->notify($user, $title, $message, $type, $link));
    }

    public function unreadCount(User $user): int
    {
        return $user->appNotifications()->unread()->count();
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
}
