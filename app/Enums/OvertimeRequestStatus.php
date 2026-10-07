<?php

namespace App\Enums;

enum OvertimeRequestStatus: string
{
    case Pending = 'pending';
    case PendingEndorsement = 'pending_endorsement';
    case PendingFinalApproval = 'pending_final_approval';
    case Approved = 'approved';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PendingEndorsement => 'Pending Endorsement',
            self::PendingFinalApproval => 'Pending Final Approval',
            self::Approved => 'Approved',
            self::Denied => 'Denied',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::PendingEndorsement, self::PendingFinalApproval], true);
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [
            self::Pending->value,
            self::PendingEndorsement->value,
            self::PendingFinalApproval->value,
        ];
    }
}
