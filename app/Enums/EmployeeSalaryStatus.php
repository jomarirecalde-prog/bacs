<?php

namespace App\Enums;

enum EmployeeSalaryStatus: string
{
    case Active = 'active';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Superseded => 'Superseded',
            self::Cancelled => 'Cancelled',
        };
    }
}
