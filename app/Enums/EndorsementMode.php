<?php

namespace App\Enums;

enum EndorsementMode: string
{
    case Sequential = 'sequential';
    case Parallel = 'parallel';

    public function label(): string
    {
        return match ($this) {
            self::Sequential => 'Sequential Endorsement',
            self::Parallel => 'Parallel Endorsement',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Sequential => 'Endorsers act one at a time in the configured order.',
            self::Parallel => 'All assigned endorsers may review and endorse independently at the same time.',
        };
    }

    public function isParallel(): bool
    {
        return $this === self::Parallel;
    }
}
