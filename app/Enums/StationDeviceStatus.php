<?php

namespace App\Enums;

enum StationDeviceStatus: string
{
    case Unbound = 'unbound';
    case Bound = 'bound';

    public function label(): string
    {
        return match ($this) {
            self::Unbound => 'Unbound',
            self::Bound => 'Bound',
        };
    }
}
