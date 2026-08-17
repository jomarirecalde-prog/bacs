<?php

namespace App\Enums;

enum QrTokenStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Disabled => 'Disabled',
            self::Revoked => 'Revoked',
        };
    }
}
