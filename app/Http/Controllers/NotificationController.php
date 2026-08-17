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
        $items = $request->user()
            ->appNotifications()
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'unread' => $this->notifications->unreadCount($request->user()),
            'items' => $items,
        ]);
    }

    public function markRead(Request $request, AppNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $this->notifications->markRead($notification);

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request)
    {
        $this->notifications->markAllRead($request->user());

        return back()->with('success', 'All notifications marked as read.');
    }
}
