<?php

namespace App\Enums;

enum ApprovalAssigneeType: string
{
    case Endorser = 'endorser';
    case SecondaryEndorser = 'secondary_endorser';
    case FinalApprover = 'final_approver';

    public function label(): string
    {
        return match ($this) {
            self::Endorser => 'Endorser',
            self::SecondaryEndorser => 'Second Endorser',
            self::FinalApprover => 'Final Approver',
        };
    }
}
