<?php

namespace App\Models;

use App\Support\ManilaTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeAttachmentActivity extends Model
{
    protected $fillable = [
        'attachment_report_id',
        'sort_order',
        'activity_description',
        'activity_date',
        'time_from',
        'time_to',
        'duration_minutes',
        'status_remark',
        'reviewed_vouched_by',
        'reviewer_user_id',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(OvertimeAttachmentReport::class, 'attachment_report_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function timeRangeLabel(): string
    {
        $from = ManilaTime::parse('1970-01-01 '.$this->time_from)->format('g:i A');
        $to = ManilaTime::parse('1970-01-01 '.$this->time_to)->format('g:i A');

        return "{$from} – {$to}";
    }

    public function durationLabel(): string
    {
        $hours = intdiv($this->duration_minutes, 60);
        $mins = $this->duration_minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours} hr {$mins} min";
        }

        if ($hours > 0) {
            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        return "{$mins} min";
    }
}
