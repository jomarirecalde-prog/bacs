<?php

namespace App\Enums;

enum BindingStatus: string
{
    case Active = 'active';
    case Unbound = 'unbound';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Unbound => 'Unbound',
        };
    }
}
