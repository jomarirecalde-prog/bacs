<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $items = $this->notifications->latest($user, 20);

        return response()->json([
            'unread' => $this->notifications->unreadCount($user),
            'items' => $items->map->toBellArray()->values(),
        ]);
    }

    public function markRead(Request $request, AppNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $this->notifications->markRead($notification);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'unread' => $this->notifications->unreadCount($request->user()),
            ]);
        }

        return back();
    }

    public function markAllRead(Request $request)
    {
        $this->notifications->markAllRead($request->user());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'unread' => 0]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }
}
