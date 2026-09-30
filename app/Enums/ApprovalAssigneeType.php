<?php

namespace App\Enums;

enum ApprovalAssigneeType: string
{
    case Endorser = 'endorser';
    case FinalApprover = 'final_approver';

    public function label(): string
    {
        return match ($this) {
            self::Endorser => 'Endorser',
            self::FinalApprover => 'Final Approver',
        };
    }
}
