<?php

namespace App\Services\Payroll;

use App\Enums\OvertimeRequestStatus;
use App\Models\Attendance;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;

class OvertimeRequestSyncService
{
    public function __construct(private readonly OvertimeCentralWorkflowService $workflow) {}

    public function syncPeriod(PayrollPeriod $period): int
    {
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $count = 0;

        Attendance::query()
            ->whereBetween('attendance_date', [$start, $end])
            ->where('overtime_minutes', '>', 0)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    $request = OvertimeRequest::query()->firstOrNew([
                        'employee_id' => $row->employee_id,
                        'attendance_date' => $row->attendance_date,
                    ]);

                    $request->attendance_id = $row->id;
                    $request->recorded_minutes = (int) $row->overtime_minutes;

                    if (! $request->exists) {
                        $request->status = OvertimeRequestStatus::Pending;
                        $request->approved_minutes = 0;
                    }

                    $request->save();
                    $this->workflow->bootstrapIfNeeded($request->fresh(['employee']));
                    $count++;
                }
            });

        return $count;
    }
}
