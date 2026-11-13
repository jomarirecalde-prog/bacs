<?php

namespace App\Support;

use App\Enums\OvertimeAttachmentReportStatus;
use App\Models\OvertimeAttachmentReport;
use Illuminate\Support\Collection;

/** OT minutes from the employee's OT Attachment Report (declared activities), not Official Time or DTR clock. */
final class DeclaredOvertimeDuration
{
    public static function minutesForDate(int $employeeId, string $date): int
    {
        return (int) self::baseQuery($employeeId)
            ->whereHas('overtimeRequest', fn ($q) => $q
                ->where('employee_id', $employeeId)
                ->whereDate('attendance_date', $date))
            ->max('total_activity_minutes');
    }

    /** @return Collection<string, int> date => minutes */
    public static function minutesByDateBetween(int $employeeId, string $start, string $end): Collection
    {
        return self::baseQuery($employeeId)
            ->whereHas('overtimeRequest', fn ($q) => $q
                ->where('employee_id', $employeeId)
                ->whereDate('attendance_date', '>=', $start)
                ->whereDate('attendance_date', '<=', $end))
            ->with('overtimeRequest:id,attendance_date')
            ->get()
            ->groupBy(fn (OvertimeAttachmentReport $row) => $row->overtimeRequest?->attendance_date?->toDateString() ?? '')
            ->filter(fn ($rows, string $date) => $date !== '')
            ->map(fn ($rows) => (int) $rows->max('total_activity_minutes'));
    }

    private static function baseQuery(int $employeeId)
    {
        return OvertimeAttachmentReport::query()
            ->where('employee_id', $employeeId)
            ->where('total_activity_minutes', '>', 0)
            ->whereNotIn('status', [
                OvertimeAttachmentReportStatus::Draft,
                OvertimeAttachmentReportStatus::Cancelled,
            ]);
    }
}
