<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;

final class DtrDayRow
{
    public function __construct(
        public readonly string $date,
        public readonly string $dateLabel,
        public readonly string $dayName,
        public readonly ?string $amIn,
        public readonly ?string $amOut,
        public readonly ?string $pmIn,
        public readonly ?string $pmOut,
        public readonly ?string $overtime,
        public readonly ?string $totalHours,
        public readonly int $overtimeMinutes,
        public readonly int $totalMinutes,
        public readonly AttendanceStatus $status,
        public readonly bool $incomplete,
        public readonly bool $isFuture,
        public readonly ?Attendance $source,
    ) {}

    public function cell(?string $value): string
    {
        return $value !== null && $value !== '' ? $value : '—';
    }

    public function pdfValue(?string $value): string
    {
        return $value ?? '';
    }

    public function isIncomplete(): bool
    {
        return $this->incomplete;
    }

    public function hasOvertime(): bool
    {
        return $this->overtimeMinutes > 0;
    }
}
