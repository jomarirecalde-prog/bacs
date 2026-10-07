<?php

namespace App\Enums;

enum PayrollComputationStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Warning => 'Warning',
            self::Error => 'Error',
        };
    }
}
