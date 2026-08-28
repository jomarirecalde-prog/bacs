<?php

namespace App\Support;

use App\Enums\AttendanceEffect;

/**
 * A resolved non-working day, regardless of whether it originated from the
 * legacy holidays table or from the calendar module.
 */
class HolidayInfo
{
    public function __construct(
        public readonly string $date,
        public readonly string $name,
        public readonly string $source,
        public readonly ?AttendanceEffect $effect = null,
        public readonly ?int $eventId = null,
    ) {}

    public function effectLabel(): string
    {
        return $this->effect?->label() ?? 'Company Holiday';
    }
}
