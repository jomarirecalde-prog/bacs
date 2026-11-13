<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\OfficialBusinessStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OfficialBusinessRequest;
use App\Support\ManilaTime;

class OfficialBusinessAttendanceService
{
    public function __construct(
        private readonly LeaveDayCalculator $days,
    ) {}

    public function applyApprovedRequest(OfficialBusinessRequest $request): void
    {
        if (! in_array($request->status, [OfficialBusinessStatus::Approved, OfficialBusinessStatus::Completed], true)) {
            return;
        }

        $request->loadMissing('participants');
        $dates = $this->days->dates(
            $request->date_covered_start->toDateString(),
            $request->date_covered_end->toDateString()
        );

        foreach ($this->affectedEmployeeIds($request) as $employeeId) {
            foreach ($dates as $date) {
                $this->markOfficialBusinessDay($employeeId, $date, $request);
            }
        }
    }

    public function clearCancelledRequest(OfficialBusinessRequest $request): void
    {
        $request->loadMissing('participants');
        $dates = $this->days->dates(
            $request->date_covered_start->toDateString(),
            $request->date_covered_end->toDateString()
        );

        foreach ($this->affectedEmployeeIds($request) as $employeeId) {
            foreach ($dates as $date) {
                $this->removeOfficialBusinessDay($employeeId, $date, $request);
            }
        }
    }

    /** Backfill attendance rows for approved OB covering this employee in a DTR period. */
    public function reconcileForEmployee(Employee $employee, string $from, string $to): void
    {
        $from = ManilaTime::parse($from)->toDateString();
        $to = ManilaTime::parse($to)->toDateString();

        OfficialBusinessRequest::query()
            ->whereIn('status', [OfficialBusinessStatus::Approved, OfficialBusinessStatus::Completed])
            ->whereDate('date_covered_start', '<=', $to)
            ->whereDate('date_covered_end', '>=', $from)
            ->where(function ($query) use ($employee) {
                $query->where('employee_id', $employee->id)
                    ->orWhereHas('participants', fn ($p) => $p->where('employee_id', $employee->id));
            })
            ->each(fn (OfficialBusinessRequest $request) => $this->applyApprovedRequest($request));
    }

    /** @return list<int> */
    private function affectedEmployeeIds(OfficialBusinessRequest $request): array
    {
        $ids = $request->participants
            ->pluck('employee_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $requesterId = (int) $request->employee_id;
        if ($requesterId > 0 && ! in_array($requesterId, $ids, true)) {
            $ids[] = $requesterId;
        }

        return $ids;
    }

    private function markOfficialBusinessDay(int $employeeId, string $date, OfficialBusinessRequest $request): void
    {
        $row = Attendance::query()
            ->where('employee_id', $employeeId)
            ->onDate($date)
            ->lockForUpdate()
            ->first();

        if ($row && $row->hasTimeIn()) {
            return;
        }

        if ($row && in_array($row->status, [AttendanceStatus::OnLeave, AttendanceStatus::TravelOrder], true)) {
            return;
        }

        $payload = [
            'employee_id' => $employeeId,
            'attendance_date' => $date,
            'status' => AttendanceStatus::OfficialBusiness->value,
            'remarks' => 'Official business '.$request->reference_number,
            'total_minutes' => 0,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'overtime_minutes' => 0,
            'am_time_in' => null,
            'am_time_out' => null,
            'pm_time_in' => null,
            'pm_time_out' => null,
            'overtime_in' => null,
            'time_in' => null,
            'time_out' => null,
        ];

        if ($row) {
            $row->update($payload);
        } else {
            Attendance::query()->create($payload);
        }

        if ($date === ManilaTime::todayDate()) {
            app(AttendanceService::class)->forgetDashboardCache($date);
        }
    }

    private function removeOfficialBusinessDay(int $employeeId, string $date, OfficialBusinessRequest $request): void
    {
        $row = Attendance::query()
            ->where('employee_id', $employeeId)
            ->onDate($date)
            ->lockForUpdate()
            ->first();

        if (! $row || $row->status !== AttendanceStatus::OfficialBusiness || $row->hasTimeIn()) {
            return;
        }

        $remarks = trim((string) $row->remarks);
        if ($remarks !== '' && ! str_contains($remarks, $request->reference_number)) {
            return;
        }

        $row->delete();

        if ($date === ManilaTime::todayDate()) {
            app(AttendanceService::class)->forgetDashboardCache($date);
        }
    }
}
