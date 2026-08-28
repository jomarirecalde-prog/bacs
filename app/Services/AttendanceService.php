<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceEdit;
use App\Models\AttendanceStation;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\User;
use App\Support\ManilaTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    /** @var array<string, array{summary: array, departments: array}> */
    private array $snapshots = [];

    /** @var Collection<int, Employee>|null */
    private $activeRoster = null;

    /** @var array<string, Collection<int, Attendance>> */
    private array $attendanceByDate = [];

    public function __construct(
        private readonly AttendanceCalculator $calculator,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
        private readonly LeaveResolver $leaves,
        private readonly HolidayResolver $holidays,
    ) {}

    public function clockIn(User $user): Attendance
    {
        $employee = $this->requireActiveEmployee($user);
        $now = ManilaTime::now();
        $date = $now->toDateString();

        return DB::transaction(function () use ($employee, $user, $now, $date) {
            $record = Attendance::query()
                ->where('employee_id', $employee->id)
                ->onDate($date)
                ->lockForUpdate()
                ->first();

            if ($record?->time_in) {
                throw ValidationException::withMessages([
                    'time_in' => 'You have already recorded Time In for today.',
                ]);
            }

            $computed = $this->calculator->calculate(
                $date,
                $now,
                null,
                $employee->schedule(),
                $employee->id
            );

            $payload = array_merge($computed, [
                'employee_id' => $employee->id,
                'attendance_date' => $date,
                'time_in' => $now,
                'time_out' => null,
                'status' => $computed['status']->value,
                'is_manual' => false,
            ]);

            if ($record) {
                $record->update($payload);
            } else {
                $record = Attendance::query()->create($payload);
            }

            $this->auditLogger->log($user, 'time_in', 'Attendance', $record->id, "Time In recorded for {$employee->fullName()} at {$now->format('h:i A')}.");
            $this->notifications->notify($user, 'Time In recorded', 'Your Time In was recorded at '.$now->format('h:i A').'.', 'success', route('employee.dashboard'));
            $this->flagPreviousIncomplete($employee, $date);

            return $record->fresh();
        });
    }

    public function clockOut(User $user): Attendance
    {
        $employee = $this->requireActiveEmployee($user);
        $now = ManilaTime::now();
        $date = $now->toDateString();

        return DB::transaction(function () use ($employee, $user, $now, $date) {
            $record = Attendance::query()
                ->where('employee_id', $employee->id)
                ->onDate($date)
                ->lockForUpdate()
                ->first();

            if (! $record || ! $record->time_in) {
                throw ValidationException::withMessages([
                    'time_out' => 'You must record Time In before Time Out.',
                ]);
            }

            if ($record->time_out) {
                throw ValidationException::withMessages([
                    'time_out' => 'You have already recorded Time Out for today.',
                ]);
            }

            if ($now->lte($record->time_in)) {
                throw ValidationException::withMessages([
                    'time_out' => 'Time Out cannot be earlier than Time In.',
                ]);
            }

            $computed = $this->calculator->calculate(
                $date,
                $record->time_in,
                $now,
                $employee->schedule(),
                $employee->id
            );

            $record->update(array_merge($computed, [
                'time_out' => $now,
                'status' => $computed['status']->value,
            ]));

            $this->auditLogger->log($user, 'time_out', 'Attendance', $record->id, "Time Out recorded for {$employee->fullName()} at {$now->format('h:i A')}.");
            $this->notifications->notify($user, 'Time Out recorded', 'Your Time Out was recorded at '.$now->format('h:i A').'.', 'success', route('employee.dashboard'));

            return $record->fresh();
        });
    }

    public function recordFromStation(AttendanceStation $station, Employee $employee): array
    {
        $now = ManilaTime::now();
        $date = $now->toDateString();

        return DB::transaction(function () use ($station, $employee, $now, $date) {
            $record = Attendance::query()
                ->where('employee_id', $employee->id)
                ->onDate($date)
                ->lockForUpdate()
                ->first();

            if ($record?->time_in && $record->time_out) {
                return $this->stationResult('ATTENDANCE_COMPLETED', false, $record, $employee);
            }

            if ($record?->time_in && ! $record->time_out) {
                $recentIn = $record->time_in->gte($now->copy()->subSeconds(10));
                if ($recentIn) {
                    return $this->stationResult('ALREADY_TIMED_IN', false, $record, $employee);
                }

                if ($now->lte($record->time_in)) {
                    return $this->stationResult('ALREADY_TIMED_IN', false, $record, $employee);
                }

                $computed = $this->calculator->calculate(
                    $date,
                    $record->time_in,
                    $now,
                    $employee->schedule(),
                    $employee->id
                );

                $record->update(array_merge($computed, [
                    'time_out' => $now,
                    'status' => $computed['status']->value,
                    'time_out_station_id' => $station->id,
                    'time_out_station_name' => $station->station_name,
                    'time_out_station_location' => $station->location,
                ]));

                $fresh = $record->fresh();
                $this->auditLogger->log(null, 'station_time_out', 'Attendance', $fresh->id, "Time Out recorded for {$employee->fullName()} at {$station->station_name} ({$now->format('h:i A')}).");
                if ($employee->user) {
                    $this->notifications->notify($employee->user, 'Time Out recorded', 'Your Time Out was recorded at '.$now->format('h:i A').' via '.$station->station_name.'.', 'success', route('employee.dashboard'));
                }

                return $this->stationResult('TIME_OUT', true, $fresh, $employee);
            }

            $computed = $this->calculator->calculate(
                $date,
                $now,
                null,
                $employee->schedule(),
                $employee->id
            );

            $payload = array_merge($computed, [
                'employee_id' => $employee->id,
                'attendance_date' => $date,
                'time_in' => $now,
                'time_out' => null,
                'status' => $computed['status']->value,
                'is_manual' => false,
                'time_in_station_id' => $station->id,
                'time_in_station_name' => $station->station_name,
                'time_in_station_location' => $station->location,
            ]);

            if ($record) {
                $record->update($payload);
            } else {
                $record = Attendance::query()->create($payload);
            }

            $fresh = $record->fresh();
            $this->auditLogger->log(null, 'station_time_in', 'Attendance', $fresh->id, "Time In recorded for {$employee->fullName()} at {$station->station_name} ({$now->format('h:i A')}).");
            if ($employee->user) {
                $this->notifications->notify($employee->user, 'Time In recorded', 'Your Time In was recorded at '.$now->format('h:i A').' via '.$station->station_name.'.', 'success', route('employee.dashboard'));
            }
            $this->flagPreviousIncomplete($employee, $date);

            return $this->stationResult('TIME_IN', true, $fresh, $employee);
        });
    }

    public function todayFor(Employee $employee): ?Attendance
    {
        return Attendance::query()
            ->where('employee_id', $employee->id)
            ->onDate(ManilaTime::todayDate())
            ->first();
    }

    public function createManual(User $actor, array $data): Attendance
    {
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $date = $data['attendance_date'];

        return DB::transaction(function () use ($actor, $employee, $date, $data) {
            $exists = Attendance::query()
                ->where('employee_id', $employee->id)
                ->onDate($date)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'attendance_date' => 'An attendance record already exists for this employee on the selected date.',
                ]);
            }

            $timeIn = ! empty($data['time_in']) ? ManilaTime::combineDateAndTime($date, $data['time_in']) : null;
            $timeOut = ! empty($data['time_out']) ? ManilaTime::combineDateAndTime($date, $data['time_out']) : null;

            if ($timeIn && $timeOut && $timeOut->lte($timeIn)) {
                throw ValidationException::withMessages([
                    'time_out' => 'Time Out must be later than Time In.',
                ]);
            }

            $forced = ! empty($data['status']) ? AttendanceStatus::from($data['status']) : null;
            if ($forced && in_array($forced, [AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::Incomplete, AttendanceStatus::Undertime, AttendanceStatus::Overtime, AttendanceStatus::HalfDay], true)) {
                $forced = null;
            }

            $computed = $this->calculator->calculate($date, $timeIn, $timeOut, $employee->schedule(), $employee->id, $forced);

            $record = Attendance::query()->create(array_merge($computed, [
                'employee_id' => $employee->id,
                'attendance_date' => $date,
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'status' => $computed['status']->value,
                'remarks' => $data['remarks'] ?? null,
                'is_manual' => true,
                'created_by' => $actor->id,
            ]));

            $this->auditLogger->log($actor, 'attendance_manual_added', 'Attendance', $record->id, "Manual DTR added for {$employee->fullName()} on {$date}.");
            $this->notifyEmployeeOfChange($employee, $date, 'A manual attendance record was added by an administrator.');

            return $record;
        });
    }

    public function updateRecord(User $actor, Attendance $attendance, array $data): Attendance
    {
        if (blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when correcting a DTR record.',
            ]);
        }

        return DB::transaction(function () use ($actor, $attendance, $data) {
            $attendance->refresh();
            $date = $attendance->attendance_date->toDateString();
            $employee = $attendance->employee;

            $timeIn = array_key_exists('time_in', $data)
                ? ($data['time_in'] ? ManilaTime::combineDateAndTime($date, $data['time_in']) : null)
                : $attendance->time_in;
            $timeOut = array_key_exists('time_out', $data)
                ? ($data['time_out'] ? ManilaTime::combineDateAndTime($date, $data['time_out']) : null)
                : $attendance->time_out;

            if ($timeIn && $timeOut && $timeOut->lte($timeIn)) {
                throw ValidationException::withMessages([
                    'time_out' => 'Time Out must be later than Time In.',
                ]);
            }

            $forced = ! empty($data['forced_status']) ? AttendanceStatus::from($data['forced_status']) : null;
            $computed = $this->calculator->calculate($date, $timeIn, $timeOut, $employee->schedule(), $employee->id, $forced);

            AttendanceEdit::query()->create([
                'attendance_id' => $attendance->id,
                'original_time_in' => $attendance->time_in,
                'original_time_out' => $attendance->time_out,
                'original_status' => $attendance->status?->value,
                'new_time_in' => $timeIn,
                'new_time_out' => $timeOut,
                'new_status' => $computed['status']->value,
                'reason' => $data['reason'],
                'modified_by' => $actor->id,
                'modified_at' => ManilaTime::now(),
            ]);

            $attendance->update(array_merge($computed, [
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'status' => $computed['status']->value,
                'remarks' => $data['remarks'] ?? $attendance->remarks,
                'is_edited' => true,
            ]));

            $this->auditLogger->log($actor, 'dtr_edited', 'Attendance', $attendance->id, "DTR edited for {$employee->fullName()} on {$date}. Reason: {$data['reason']}");
            $this->notifyEmployeeOfChange($employee, $date, 'Your DTR was modified by an administrator. Reason: '.$data['reason']);
            $this->notifications->notifyAdmins(
                'DTR modified',
                "{$actor->name} modified the DTR of {$employee->fullName()} for {$date}.",
                'warning',
                route('admin.dtr.show', $attendance)
            );

            return $attendance->fresh(['employee', 'edits.modifier']);
        });
    }

    public function monthlyDtr(Employee $employee, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1, 0, 0, 0, ManilaTime::TIMEZONE)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $today = ManilaTime::today();

        $records = Attendance::query()
            ->where('employee_id', $employee->id)
            ->betweenDates($start->toDateString(), $end->toDateString())
            ->get()
            ->keyBy(fn (Attendance $row) => $row->attendance_date->toDateString());

        $this->leaves->loadForEmployee($employee->id, $start->toDateString(), $end->toDateString());

        $rows = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $existing = $records->get($date);

            if ($existing) {
                $rows[] = $existing;
            } elseif ($cursor->lte($today)) {
                $computed = $this->calculator->calculate($date, null, null, $employee->schedule(), $employee->id);
                $rows[] = new Attendance(array_merge($computed, [
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                    'status' => $computed['status']->value,
                ]));
            } else {
                $rows[] = new Attendance([
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                    'status' => AttendanceStatus::Absent->value,
                ]);
            }

            $cursor->addDay();
        }

        return $rows;
    }

    public function dashboardSummary(?string $date = null): array
    {
        return $this->dashboardSnapshot($date)['summary'];
    }

    public function departmentSummaries(?string $date = null): array
    {
        return $this->dashboardSnapshot($date)['departments'];
    }

    /**
     * One pass over the active roster: summary cards and per-department
     * totals share the same employee, attendance, and leave loads.
     *
     * @return array{summary: array, departments: array}
     */
    public function dashboardSnapshot(?string $date = null): array
    {
        $date ??= ManilaTime::todayDate();

        if (isset($this->snapshots[$date])) {
            return $this->snapshots[$date];
        }

        $now = ManilaTime::now();
        $employees = $this->activeEmployees();
        $attendance = $this->attendanceForDate($date);
        $this->leaves->loadForDate($employees->pluck('id'), $date);
        $this->holidays->rememberEmployees($employees);

        $summary = [
            'date' => $date,
            'total_employees' => $employees->count(),
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'on_leave' => 0,
            'missing_timeout' => 0,
            'clocked_in' => 0,
            'completed' => 0,
        ];
        $groups = [];

        foreach ($employees as $employee) {
            $bucket = $this->classify($employee, $attendance->get($employee->id), $date, $now);
            $summary['present'] += $bucket['present'] ? 1 : 0;
            $summary['late'] += $bucket['late'] ? 1 : 0;
            $summary['absent'] += $bucket['absent'] ? 1 : 0;
            $summary['on_leave'] += $bucket['on_leave'] ? 1 : 0;
            $summary['clocked_in'] += $bucket['working'] ? 1 : 0;
            $summary['completed'] += $bucket['completed'] ? 1 : 0;
            $summary['missing_timeout'] += $bucket['missing_timeout'] ? 1 : 0;

            $name = $employee->department?->name ?? 'Unassigned';
            $sort = $employee->department?->sort_order ?? 999;
            $groups[$name] ??= [
                'department' => $name,
                'sort_order' => $sort,
                'employees' => 0,
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'working' => 0,
            ];
            $groups[$name]['employees']++;
            $groups[$name]['present'] += $bucket['present'] ? 1 : 0;
            $groups[$name]['late'] += $bucket['late'] ? 1 : 0;
            $groups[$name]['absent'] += $bucket['absent'] ? 1 : 0;
            $groups[$name]['working'] += $bucket['working'] ? 1 : 0;
        }

        return $this->snapshots[$date] = [
            'summary' => $summary,
            'departments' => collect($groups)->sortBy('sort_order')->values()->all(),
        ];
    }

    public function monthlySummary(Employee $employee, ?int $year = null, ?int $month = null): array
    {
        $year ??= (int) ManilaTime::now()->year;
        $month ??= (int) ManilaTime::now()->month;
        $rows = $this->monthlyDtr($employee, $year, $month);
        $today = ManilaTime::today();

        $present = $late = $absent = $missing = 0;
        $lateMinutes = $undertime = $overtime = 0;

        foreach ($rows as $row) {
            if ($row->attendance_date->gt($today)) {
                continue;
            }

            if ($row->hasTimeIn()) {
                $present++;
            }
            if ($row->late_minutes > 0) {
                $late++;
            }
            if ($row->status === AttendanceStatus::Absent) {
                $absent++;
            }
            if ($row->hasTimeIn() && ! $row->hasTimeOut()) {
                $missing++;
            }

            $lateMinutes += (int) $row->late_minutes;
            $undertime += (int) $row->undertime_minutes;
            $overtime += (int) $row->overtime_minutes;
        }

        return [
            'year' => $year,
            'month' => $month,
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertime,
            'overtime_minutes' => $overtime,
            'missing_timeout' => $missing,
        ];
    }

    private function activeEmployees()
    {
        return $this->activeRoster ??= Employee::query()
            ->with(['department:id,name,sort_order', 'workSchedule'])
            ->active()
            ->get();
    }

    private function attendanceForDate(string $date)
    {
        return $this->attendanceByDate[$date] ??= Attendance::query()
            ->onDate($date)
            ->get()
            ->keyBy('employee_id');
    }

    private function classify(Employee $employee, ?Attendance $row, string $date, Carbon $now): array
    {
        $flags = [
            'present' => false,
            'late' => false,
            'absent' => false,
            'on_leave' => false,
            'working' => false,
            'completed' => false,
            'missing_timeout' => false,
        ];

        if (! $row) {
            if ($this->leaves->approvedOn($employee->id, $date)) {
                $flags['on_leave'] = true;

                return $flags;
            }

            if ($this->holidays->isNonWorking($date, $employee) || ! $employee->schedule()->isWorkDay((int) ManilaTime::parse($date)->isoWeekday())) {
                return $flags;
            }

            $flags['absent'] = true;

            return $flags;
        }

        if ($row->status === AttendanceStatus::OnLeave) {
            $flags['on_leave'] = true;

            return $flags;
        }

        if ($row->hasTimeIn()) {
            $flags['present'] = true;
            $flags['late'] = $row->late_minutes > 0;
            $flags['completed'] = $row->hasTimeOut();

            if (! $row->hasTimeOut()) {
                $workEnd = ManilaTime::combineDateAndTime($date, (string) $employee->schedule()->end_time);
                if ($now->greaterThan($workEnd) || $date < $now->toDateString()) {
                    $flags['missing_timeout'] = true;
                } else {
                    $flags['working'] = true;
                }
            }
        } elseif ($row->status === AttendanceStatus::Absent) {
            $flags['absent'] = true;
        }

        return $flags;
    }

    private function stationResult(string $code, bool $recorded, Attendance $attendance, Employee $employee): array
    {
        return [
            'code' => $code,
            'recorded' => $recorded,
            'action' => in_array($code, ['TIME_IN', 'TIME_OUT'], true) ? $code : null,
            'attendance' => $attendance,
            'employee' => $employee,
        ];
    }

    private function requireActiveEmployee(User $user): Employee
    {
        $employee = $user->employee;

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee' => 'Your account is not linked to an employee profile.',
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'employee' => 'Your account is not active.',
            ]);
        }

        return $employee;
    }

    private function notifyEmployeeOfChange(Employee $employee, string $date, string $message): void
    {
        if ($employee->user) {
            $this->notifications->notify(
                $employee->user,
                'DTR updated by administrator',
                $message.' Date: '.$date,
                'warning',
                route('employee.dtr')
            );
        }
    }

    private function flagPreviousIncomplete(Employee $employee, string $today): void
    {
        $previous = Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('attendance_date', '<', $today)
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->orderByDesc('attendance_date')
            ->first();

        if (! $previous) {
            return;
        }

        $this->notifications->notifyAdmins(
            'Employee forgot to Time Out',
            "{$employee->fullName()} has a missing Time Out on {$previous->attendance_date->toFormattedDateString()}.",
            'danger',
            route('admin.dtr.index', ['date' => $previous->attendance_date->toDateString()])
        );

        if ($employee->user) {
            $this->notifications->notify(
                $employee->user,
                'Missing Time Out',
                'You have a missing Time Out on '.$previous->attendance_date->toFormattedDateString().'. Please contact your administrator.',
                'warning',
                route('employee.dtr')
            );
        }
    }
}
