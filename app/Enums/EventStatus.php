<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Published => 'brand',
            self::Draft => 'warn',
            self::Cancelled => 'neutral',
        };
    }

    /**
     * Drafts stay internal. Cancelled events remain visible so employees who
     * already planned around a meeting can see it was called off rather than
     * watch it silently disappear — but only Published events ever influence
     * attendance or trigger notifications.
     */
    public function isVisibleToEmployees(): bool
    {
        return $this !== self::Draft;
    }

    /**
     * @return array<int, string>
     */
    public static function employeeVisibleValues(): array
    {
        return collect(self::cases())
            ->filter(fn (self $status) => $status->isVisibleToEmployees())
            ->map(fn (self $status) => $status->value)
            ->values()
            ->all();
    }
}
