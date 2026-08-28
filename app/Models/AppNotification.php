<?php

namespace App\Models;

use App\Support\ManilaTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'link',
        'calendar_event_id',
        'leave_application_id',
        'action',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(LeaveApplication::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * @return array<string, mixed>
     */
    public function toBellArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'link' => $this->link,
            'unread' => $this->isUnread(),
            'action' => $this->action,
            'calendar_event_id' => $this->calendar_event_id,
            'leave_application_id' => $this->leave_application_id,
            'created_at' => ManilaTime::formatDateTime($this->created_at, 'M j, Y g:i A'),
        ];
    }
}
