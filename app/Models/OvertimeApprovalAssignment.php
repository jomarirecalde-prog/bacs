<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeApprovalAssignment extends Model
{
    protected $fillable = [
        'overtime_request_id',
        'stage',
        'user_id',
        'employee_id',
        'approver_name',
        'approver_position',
        'status',
        'reason',
        'acted_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function overtimeRequest(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isDenied(): bool
    {
        return $this->status === 'denied';
    }
}
