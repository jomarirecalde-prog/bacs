<?php

namespace App\Events;

use App\Models\CalendarEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an authorised viewer that a calendar event they can see has changed.
 * Contains no description, audience, or other sensitive fields — just enough
 * for the open calendar page to refetch its own filtered feed.
 */
class CalendarEventChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $userId,
        public CalendarEvent $event,
        public string $action,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'calendar.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'event_id' => $this->event->id,
            'start_date' => $this->event->start_date?->toDateString(),
            'end_date' => $this->event->end_date?->toDateString(),
        ];
    }
}
