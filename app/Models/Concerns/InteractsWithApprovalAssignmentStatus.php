<?php

namespace App\Models\Concerns;

use App\Enums\LeaveDecision;

trait InteractsWithApprovalAssignmentStatus
{
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }

    public function isApproved(): bool
    {
        return $this->status === LeaveDecision::Approved->value;
    }

    public function isDenied(): bool
    {
        return $this->status === LeaveDecision::Denied->value;
    }
}
