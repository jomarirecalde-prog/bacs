<?php

namespace App\Models;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\OvertimeAttachmentReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeAttachmentApprovalAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'overtime_attachment_report_id',
        'assignment_id',
        'user_id',
        'stage',
        'action',
        'decision',
        'previous_status',
        'new_status',
        'reason',
        'ip_address',
        'user_agent',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'stage' => LeaveApprovalStage::class,
            'decision' => LeaveDecision::class,
            'previous_status' => OvertimeAttachmentReportStatus::class,
            'new_status' => OvertimeAttachmentReportStatus::class,
            'acted_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(OvertimeAttachmentReport::class, 'overtime_attachment_report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(OvertimeAttachmentApprovalAssignment::class, 'assignment_id');
    }
}
