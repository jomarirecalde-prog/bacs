<?php

namespace App\Enums;

enum AttendanceEffect: string
{
    case NoAttendanceRequired = 'no_attendance_required';
    case CompanyHoliday = 'company_holiday';
    case SpecialNonWorking = 'special_non_working';
    case Informational = 'informational';

    public function label(): string
    {
        return match ($this) {
            self::NoAttendanceRequired => 'No Attendance Required',
            self::CompanyHoliday => 'Company Holiday',
            self::SpecialNonWorking => 'Special Non-Working Day',
            self::Informational => 'Informational Only',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NoAttendanceRequired => 'Employees are not expected to time in. Absences are not counted.',
            self::CompanyHoliday => 'Treated as an official company holiday in the DTR and reports.',
            self::SpecialNonWorking => 'Treated as a special non-working day in the DTR and reports.',
            self::Informational => 'Shown on the calendar only. Attendance is unaffected.',
        };
    }

    /**
     * Whether the effect suppresses absence and marks the day non-working
     * for attendance, DTR, and reporting purposes.
     */
    public function isNonWorking(): bool
    {
        return $this !== self::Informational;
    }

    public function tone(): string
    {
        return match ($this) {
            self::NoAttendanceRequired, self::CompanyHoliday => 'brand',
            self::SpecialNonWorking => 'gold',
            self::Informational => 'neutral',
        };
    }
}
