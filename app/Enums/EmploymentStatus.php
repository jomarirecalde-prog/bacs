<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Regular = 'regular';
    case Probationary = 'probationary';
    case Contractual = 'contractual';
    case Intern = 'intern';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Probationary => 'Probationary',
            self::Contractual => 'Contractual',
            self::Intern => 'Intern',
        };
    }
}
