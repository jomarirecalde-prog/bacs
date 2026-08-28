<?php

namespace App\Enums;

enum EventAudience: string
{
    case All = 'all';
    case Departments = 'departments';
    case Employees = 'employees';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All Employees',
            self::Departments => 'Specific Department',
            self::Employees => 'Specific Employees',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::All => 'brand',
            self::Departments => 'info',
            self::Employees => 'gold',
        };
    }
}
