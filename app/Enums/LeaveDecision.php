<?php

namespace App\Enums;

enum LeaveDecision: string
{
    case Approved = 'approved';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::Denied => 'Denied',
        };
    }
}
