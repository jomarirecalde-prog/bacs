<?php

namespace App\Enums;

enum AttendanceCorrectionStatus: string
{
    case Pending = 'pending';
    case PendingEndorsement = 'pending_endorsement';
    case PendingFinalApproval = 'pending_final_approval';
    case PendingApproverAssignment = 'pending_approver_assignment';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::PendingEndorsement => 'Pending Endorsement',
            self::PendingFinalApproval => 'Pending Final Approval',
            self::PendingApproverAssignment => 'Pending Approver Assignment',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending,
            self::PendingEndorsement,
            self::PendingFinalApproval,
            self::PendingApproverAssignment => 'yellow',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Cancelled => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::PendingEndorsement, self::PendingFinalApproval, self::PendingApproverAssignment], true);
    }
}
