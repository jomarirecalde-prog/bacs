<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\TravelOrderStatus;
use App\Models\Attendance;
use App\Models\TravelOrder;
use App\Support\ManilaTime;

class TravelOrderAttendanceService
{
    public function __construct(
        private readonly LeaveDayCalculator $days,
    ) {}

    public function applyApprovedOrder(TravelOrder $order): void
    {
        if ($order->status !== TravelOrderStatus::Approved) {
            return;
        }

        $order->loadMissing('personnel');
        $dates = $this->days->dates(
            $order->date_start->toDateString(),
            $order->date_end->toDateString()
        );

        foreach ($order->personnel as $person) {
            $employeeId = (int) $person->employee_id;
            foreach ($dates as $date) {
                $this->markTravelDay($employeeId, $date, $order);
            }
        }
    }

    public function clearCancelledOrder(TravelOrder $order): void
    {
        $order->loadMissing('personnel');
        $dates = $this->days->dates(
            $order->date_start->toDateString(),
            $order->date_end->toDateString()
        );

        foreach ($order->personnel as $person) {
            $employeeId = (int) $person->employee_id;
            foreach ($dates as $date) {
                $this->removeTravelDay($employeeId, $date, $order);
            }
        }
    }

    private function markTravelDay(int $employeeId, string $date, TravelOrder $order): void
    {
        $row = Attendance::query()
            ->where('employee_id', $employeeId)
            ->onDate($date)
            ->lockForUpdate()
            ->first();

        if ($row && $row->hasTimeIn()) {
            return;
        }

        $payload = [
            'employee_id' => $employeeId,
            'attendance_date' => $date,
            'status' => AttendanceStatus::TravelOrder->value,
            'remarks' => 'Travel order '.$order->travel_order_number,
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

    private function removeTravelDay(int $employeeId, string $date, TravelOrder $order): void
    {
        $row = Attendance::query()
            ->where('employee_id', $employeeId)
            ->onDate($date)
            ->lockForUpdate()
            ->first();

        if (! $row || $row->status !== AttendanceStatus::TravelOrder || $row->hasTimeIn()) {
            return;
        }

        $remarks = trim((string) $row->remarks);
        if ($remarks !== '' && ! str_contains($remarks, $order->travel_order_number)) {
            return;
        }

        $row->delete();

        if ($date === ManilaTime::todayDate()) {
            app(AttendanceService::class)->forgetDashboardCache($date);
        }
    }
}
