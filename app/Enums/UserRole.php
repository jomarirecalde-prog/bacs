<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Super Admin',
            self::Supervisor => 'Boss / Management',
            self::Employee => 'Employee',
        };
    }

    public function isManagement(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor], true);
    }
}
