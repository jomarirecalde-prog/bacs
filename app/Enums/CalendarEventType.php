<?php

namespace App\Enums;

enum CalendarEventType: string
{
    case Holiday = 'holiday';
    case SpecialNonWorking = 'special_non_working';
    case Meeting = 'meeting';
    case Announcement = 'announcement';
    case CompanyEvent = 'company_event';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Holiday => 'Holiday',
            self::SpecialNonWorking => 'Special Non-Working Day',
            self::Meeting => 'Company Meeting',
            self::Announcement => 'Employee Announcement',
            self::CompanyEvent => 'Company Event',
            self::Other => 'Other Important Event',
        };
    }

    /**
     * Short label for dense surfaces such as month-view chips.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Holiday => 'Holiday',
            self::SpecialNonWorking => 'Non-Working',
            self::Meeting => 'Meeting',
            self::Announcement => 'Announcement',
            self::CompanyEvent => 'Event',
            self::Other => 'Other',
        };
    }

    /**
     * Maps to the semantic colour scales defined in resources/css/app.css.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Holiday, self::SpecialNonWorking => 'brand',
            self::Meeting => 'info',
            self::Announcement => 'warn',
            self::CompanyEvent => 'gold',
            self::Other => 'neutral',
        };
    }

    /**
     * Heroicons outline path, mirroring the icon approach used across the app shell.
     * Types never rely on colour alone to be distinguishable.
     */
    public function iconPath(): string
    {
        return match ($this) {
            self::Holiday => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
            self::SpecialNonWorking => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2zm4-6l4 4m0-4l-4 4',
            self::Meeting => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
            self::Announcement => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z',
            self::CompanyEvent => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
            self::Other => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        };
    }

    /**
     * Only day-classification types may carry an attendance effect.
     */
    public function supportsAttendanceEffect(): bool
    {
        return in_array($this, [self::Holiday, self::SpecialNonWorking], true);
    }
}
