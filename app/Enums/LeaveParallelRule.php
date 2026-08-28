<?php

namespace App\Enums;

enum LeaveParallelRule: string
{
    case All = 'all';
    case Any = 'any';
    case Majority = 'majority';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All required approvers must approve',
            self::Any => 'Any one authorized approver can approve',
            self::Majority => 'Majority of assigned approvers must approve',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::All => 'Every Immediate Supervisor/Superior must independently approve before the request advances.',
            self::Any => 'The first Immediate Supervisor/Superior approval completes the parallel stage.',
            self::Majority => 'More than half of the assigned Immediate Supervisors/Superiors must approve.',
        };
    }
}
