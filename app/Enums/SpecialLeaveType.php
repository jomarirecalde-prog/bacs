<?php

namespace App\Enums;

enum SpecialLeaveType: string
{
    case Maternity = 'maternity';
    case Paternity = 'paternity';
    case MagnaCarta = 'magna_carta';
    case Vawc = 'vawc';
    case SoloParent = 'solo_parent';

    public function label(): string
    {
        return match ($this) {
            self::Maternity => 'Maternity Leave',
            self::Paternity => 'Paternity Leave',
            self::MagnaCarta => 'Magna Carta for Women',
            self::Vawc => 'VAWC',
            self::SoloParent => 'Solo Parent',
        };
    }

    public function defaultDays(): int
    {
        return match ($this) {
            self::Maternity => 105,
            self::Paternity => 7,
            self::MagnaCarta => 60,
            self::Vawc => 10,
            self::SoloParent => 7,
        };
    }

    public function countsCalendarDays(): bool
    {
        return true;
    }
}
