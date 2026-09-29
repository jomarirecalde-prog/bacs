<?php

namespace App\Models;

use App\Enums\EmailNotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailNotificationLog extends Model
{
    protected $fillable = [
        'notification_type',
        'recipient',
        'subject',
        'dedupe_key',
        'delivery_status',
        'notifiable_type',
        'notifiable_id',
        'user_id',
        'error_message',
        'meta',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'notification_type' => EmailNotificationType::class,
            'meta' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
