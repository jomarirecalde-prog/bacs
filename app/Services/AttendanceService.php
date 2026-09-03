<?php

namespace App\Services;

use App\Enums\AttendancePunchType;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceEdit;
use App\Models\AttendanceStation;
use App\Models\Employee;
use App\Models\User;
use App\Support\ManilaTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
        private readonly AttendanceSequenceService $sequence,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifications,
        private readonly LeaveResolver $leaves,
        private readonly HolidayResolver $holidays,
    ) {}

    public function clockIn(User $user): Attendance
    {
        return $this->recordNextPunch($user, null);
    }

    public function clockOut(User $user): Attendance
    {
        return $this->recordNextPunch($user, null);
    }

    public function recordNextPunch(User $user, ?AttendanceStation $station = null): Attendance
    {
        $employee = $this->requireActiveEmployee($user);

        return $this->recordPunchForEmployee($employee, $station, $user);
    }

    public function recordFromStation(AttendanceStation $station, Employee $employee): array
    {
        $now = ManilaTime::now();
        $date = $now->toDateString();

        return DB::transaction(function () use ($station, $employee, $now, $date) {
            if ($message = $this->pendingCorrectionMessage($employee, $date)) {
                return $this->stationResult(
                    'PENDING_CORRECTION',
                    false,
                    new Attendance(['employee_id' => $employee->id, 'attendance_date' => $date]),
                    $employee,
                    $now,
                    $employee->schedule(),
                    null,
                    $message
                );
            }

            $record = $this->lockTodayRecord($employee, $date);
            $schedule = $employee->schedule();
            $resolution = $this->sequence->resolveScan($record, $now, $schedule);

            if (! $resolution['type']) {
                return $this->stationResult(
                    $resolution['code'] ?? 'ATTENDANCE_COMPLETED',
                    false,
                    $record ?? new Attendance(['employee_id' => $employee->id, 'attendance_date' => $date]),
                    $employee,
                    $now,
                    $schedule,
                    null
                );
            }

            $type = $resolution['type'];
            $record = $this->applyPunch($record, $employee, $type, $now, $station);

            return $this->stationResult($type->scanCode(), true, $record, $employee, $now, $schedule, $type);
        });
    }

    public function todayFor(Employee $employee): ?Attendance
    {
        return Attendance::query()
            ->where('employee_id', $employee->id)
            ->onDate(ManilaTime::todayDate())
            ->first();
    }

    public function nextExpectedFor(Employee $employee, ?Attendance $today = null): ?AttendancePunchType
    {
        return $this->sequence->nextExpected($today ?? $this->todayFor($employee));
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

            $punches = $this->punchesFromInput($date, $data);
            $forced = ! empty($data['status']) ? AttendanceStatus::from($data['status']) : null;
            if ($forced && in_array($forced, [AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::Incomplete, AttendanceStatus::Undertime, AttendanceStatus::Overtime, AttendanceStatus::HalfDay], true)) {
                $forced = null;
            }

            $computed = $this->calculator->calculateFromPunches($date, $punches, $employee->schedule(), $employee->id, $forced);

            $record = Attendance::query()->create(array_merge($computed, $punches, [
                'employee_id' => $employee->id,
                'attendance_date' => $date,
                'status' => $computed['status']->value,
                'remarks' => $data['remarks'] ?? null,
                'is_manual' => true,
                'created_by' => $actor->id,
            ]));

            $record->syncLegacyFields();
            $record->save();

            $this->auditLogger->log($actor, 'attendance_manual_added', 'Attendance', $record->id, "Manual DTR added for {$employee->fullName()} on {$date}.");
            $this->notifyEmployeeOfChange($employee, $date, 'A manual attendance record was added by an administrator.');
            $this->forgetDashboardCache($date);

            return $record->fresh();
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

            $original = $this->currentPunches($attendance);
            $updated = $this->mergePunchInput($date, $original, $data);
            $this->validatePunchOrder($updated);

            $forced = ! empty($data['forced_status']) ? AttendanceStatus::from($data['forced_status']) : null;
            $computed = $this->calculator->calculateFromPunches($date, $updated, $employee->schedule(), $employee->id, $forced);

            $fieldChanges = $this->buildFieldChanges($original, $updated);

            AttendanceEdit::query()->create([
                'attendance_id' => $attendance->id,
                'original_time_in' => $original['am_time_in'],
                'original_time_out' => $original['pm_time_out'],
                'original_status' => $attendance->status?->value,
                'new_time_in' => $updated['am_time_in'],
                'new_time_out' => $updated['pm_time_out'],
                'new_status' => $computed['status']->value,
                'field_changes' => $fieldChanges,
                'reason' => $data['reason'],
                'modified_by' => $actor->id,
                'modified_at' => ManilaTime::now(),
            ]);

            $attendance->update(array_merge($computed, $updated, [
                'status' => $computed['status']->value,
                'remarks' => $data['remarks'] ?? $attendance->remarks,
                'is_edited' => true,
            ]));

            $attendance->syncLegacyFields();
            $attendance->save();

            $this->auditLogger->log($actor, 'dtr_edited', 'Attendance', $attendance->id, "DTR edited for {$employee->fullName()} on {$date}. Reason: {$data['reason']}");
            $this->notifyEmployeeOfChange($employee, $date, 'Your DTR was modified by an administrator. Reason: '.$data['reason']);
            $this->notifications->notifyAdmins(
                'DTR modified',
                "{$actor->name} modified the DTR of {$employee->fullName()} for {$date}.",
                'warning',
                route('admin.dtr.show', $attendance)
            );
            $this->forgetDashboardCache($date);

            return $attendance->fresh(['employee', 'edits.modifier']);
        });
    }

    public function monthlyDtr(Employee $employee, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1, 0, 0, 0, ManilaTime::TIMEZONE)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return $this->rangeDtr($employee, $start->toDateString(), $end->toDateString());
    }

    /** @return list<Attendance> */
    public function rangeDtr(Employee $employee, string $from, string $to): array
    {
        $start = ManilaTime::parse($from)->startOfDay();
        $end = ManilaTime::parse($to)->startOfDay();
        if ($end->lt($start)) {
            return [];
        }

        $today = ManilaTime::today();
        $schedule = $employee->schedule();

        $records = Attendance::query()
            ->where('employee_id', $employee->id)
            ->betweenDates($start->toDateString(), $end->toDateString())
            ->get([
                'id',
                'employee_id',
                'attendance_date',
                'am_time_in',
                'am_time_out',
                'pm_time_in',
                'pm_time_out',
                'overtime_in',
                'time_in',
                'time_out',
                'total_minutes',
                'late_minutes',
                'undertime_minutes',
                'overtime_minutes',
                'status',
                'remarks',
                'is_manual',
                'is_edited',
            ])
            ->keyBy(fn (Attendance $row) => $row->attendance_date->toDateString());

        $this->leaves->loadForEmployee($employee->id, $start->toDateString(), $end->toDateString());
        $this->holidays->rememberEmployees([$employee]);

        $rows = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $existing = $records->get($date);

            if ($existing) {
                $rows[] = $existing;
            } elseif ($cursor->lte($today)) {
                // Empty past days: resolve leave/holiday/rest/absent without the
                // full punch calculator (same business rules, far less work).
                $status = $this->statusForEmptyDay($employee, $date, $schedule);
                $rows[] = new Attendance([
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                    'status' => $status->value,
                    'total_minutes' => 0,
                    'late_minutes' => 0,
                    'undertime_minutes' => 0,
                    'overtime_minutes' => 0,
                ]);
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

    /** @return array{summary: array, departments: array} */
    public function dashboardSnapshot(?string $date = null): array
    {
        $date ??= ManilaTime::todayDate();

        if (isset($this->snapshots[$date])) {
            return $this->snapshots[$date];
        }

        $ttl = (int) config('performance.dashboard_snapshot_ttl', 15);
        $today = ManilaTime::todayDate();

        if ($ttl > 0 && $date === $today) {
            return $this->snapshots[$date] = Cache::remember(
                $this->dashboardCacheKey($date),
                $ttl,
                fn () => $this->buildDashboardSnapshot($date)
            );
        }

        return $this->snapshots[$date] = $this->buildDashboardSnapshot($date);
    }

    public function forgetDashboardCache(?string $date = null): void
    {
        $date ??= ManilaTime::todayDate();
        unset($this->snapshots[$date], $this->attendanceByDate[$date]);
        $this->activeRoster = null;
        Cache::forget($this->dashboardCacheKey($date));
    }

    /** @return array{summary: array, departments: array} */
    private function buildDashboardSnapshot(string $date): array
    {
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

        return [
            'summary' => $summary,
            'departments' => collect($groups)->sortBy('sort_order')->values()->all(),
        ];
    }

    private function dashboardCacheKey(string $date): string
    {
        return 'dashboard:snapshot:'.$date;
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
            if ($row->hasTimeIn() && ! $row->isRegularComplete()) {
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

    private function recordPunchForEmployee(Employee $employee, ?AttendanceStation $station, ?User $user): Attendance
    {
        $now = ManilaTime::now();
        $date = $now->toDateString();

        return DB::transaction(function () use ($employee, $station, $user, $now, $date) {
            if ($message = $this->pendingCorrectionMessage($employee, $date)) {
                throw ValidationException::withMessages([
                    'attendance' => $message,
                ]);
            }

            $record = $this->lockTodayRecord($employee, $date);
            $schedule = $employee->schedule();
            $resolution = $this->sequence->resolveScan($record, $now, $schedule);

            if (! $resolution['type']) {
                throw ValidationException::withMessages([
                    'attendance' => $resolution['message'] ?? 'Attendance cannot be recorded at this time.',
                ]);
            }

            $type = $resolution['type'];
            $record = $this->applyPunch($record, $employee, $type, $now, $station);

            $this->auditLogger->log(
                $user,
                $type->value,
                'Attendance',
                $record->id,
                "{$type->label()} recorded for {$employee->fullName()} at {$now->format('h:i A')}."
            );

            if ($user) {
                $this->notifications->notify(
                    $user,
                    $type->label().' recorded',
                    'Your '.$type->label().' was recorded at '.$now->format('h:i A').'.',
                    'success',
                    route('employee.dashboard')
                );
            }

            if ($type === AttendancePunchType::AmTimeIn) {
                $this->flagPreviousIncompleteFor($employee, $date);
            }

            return $record->fresh();
        });
    }

    private function applyPunch(?Attendance $record, Employee $employee, AttendancePunchType $type, Carbon $now, ?AttendanceStation $station): Attendance
    {
        $date = $now->toDateString();
        $schedule = $employee->schedule();
        $punches = $record ? $this->currentPunches($record) : $this->emptyPunches();
        $punches[$type->column()] = $now;

        $computed = $this->calculator->calculateFromPunches($date, $punches, $schedule, $employee->id);
        $payload = array_merge($computed, $punches, [
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'status' => $computed['status']->value,
            'is_manual' => false,
        ]);

        if ($station) {
            $stations = $record?->punch_stations ?? [];
            $stations[$type->value] = [
                'station_id' => $station->id,
                'station_name' => $station->station_name,
                'station_location' => $station->location,
            ];
            $payload['punch_stations'] = $stations;
        }

        if ($record) {
            $record->update($payload);
        } else {
            $record = Attendance::query()->create($payload);
        }

        $record->syncLegacyFields();
        $record->save();
        $this->forgetDashboardCache($date);

        return $record;
    }

    private function statusForEmptyDay(Employee $employee, string $date, $schedule): AttendanceStatus
    {
        if ($this->leaves->approvedOn($employee->id, $date)) {
            return AttendanceStatus::OnLeave;
        }

        if ($this->holidays->isNonWorking($date, $employee)) {
            return AttendanceStatus::Holiday;
        }

        if (! $schedule->isWorkDay((int) ManilaTime::parse($date)->isoWeekday())) {
            return AttendanceStatus::RestDay;
        }

        return AttendanceStatus::Absent;
    }

    private function lockTodayRecord(Employee $employee, string $date): ?Attendance
    {
        return Attendance::query()
            ->where('employee_id', $employee->id)
            ->onDate($date)
            ->lockForUpdate()
            ->first();
    }

    /** @return array<string, ?Carbon> */
    private function currentPunches(Attendance $record): array
    {
        return [
            'am_time_in' => $record->am_time_in,
            'am_time_out' => $record->am_time_out,
            'pm_time_in' => $record->pm_time_in,
            'pm_time_out' => $record->pm_time_out,
            'overtime_in' => $record->overtime_in,
        ];
    }

    /** @return array<string, null> */
    private function emptyPunches(): array
    {
        return [
            'am_time_in' => null,
            'am_time_out' => null,
            'pm_time_in' => null,
            'pm_time_out' => null,
            'overtime_in' => null,
        ];
    }

    /** @return array<string, ?Carbon> */
    private function punchesFromInput(string $date, array $data): array
    {
        return [
            'am_time_in' => $this->inputPunch($date, $data, 'am_time_in', 'time_in'),
            'am_time_out' => $this->inputPunch($date, $data, 'am_time_out'),
            'pm_time_in' => $this->inputPunch($date, $data, 'pm_time_in'),
            'pm_time_out' => $this->inputPunch($date, $data, 'pm_time_out', 'time_out'),
            'overtime_in' => $this->inputPunch($date, $data, 'overtime_in', 'overtime'),
        ];
    }

    /** @param  array<string, ?Carbon>  $original */
    private function mergePunchInput(string $date, array $original, array $data): array
    {
        $merged = $original;

        foreach (AttendancePunchType::cases() as $type) {
            $field = $type->column();
            if (! array_key_exists($field, $data) && ! ($field === 'am_time_in' && array_key_exists('time_in', $data)) && ! ($field === 'pm_time_out' && array_key_exists('time_out', $data))) {
                continue;
            }

            $legacy = match ($field) {
                'am_time_in' => 'time_in',
                'pm_time_out' => 'time_out',
                default => null,
            };

            $raw = $data[$field] ?? ($legacy ? ($data[$legacy] ?? null) : null);
            $merged[$field] = filled($raw) ? ManilaTime::combineDateAndTime($date, $raw) : null;
        }

        return $merged;
    }

    private function inputPunch(string $date, array $data, string $field, ?string $legacy = null): ?Carbon
    {
        $raw = $data[$field] ?? ($legacy ? ($data[$legacy] ?? null) : null);

        return filled($raw) ? ManilaTime::combineDateAndTime($date, $raw) : null;
    }

    /** @param  array<string, ?Carbon>  $punches */
    private function validatePunchOrder(array $punches): void
    {
        $pairs = [
            ['am_time_in', 'am_time_out', 'AM Time Out must be later than AM Time In.'],
            ['pm_time_in', 'pm_time_out', 'PM Time Out must be later than PM Time In.'],
        ];

        foreach ($pairs as [$start, $end, $message]) {
            if ($punches[$start] && $punches[$end] && $punches[$end]->lte($punches[$start])) {
                throw ValidationException::withMessages([$end => $message]);
            }
        }

        if ($punches['overtime_in'] && $punches['pm_time_out'] && $punches['overtime_in']->lte($punches['pm_time_out'])) {
            throw ValidationException::withMessages([
                'overtime_in' => 'Overtime must be later than PM Time Out.',
            ]);
        }
    }

    /** @param  array<string, ?Carbon>  $before @param  array<string, ?Carbon>  $after */
    private function buildFieldChanges(array $before, array $after): array
    {
        $changes = [];

        foreach (AttendancePunchType::cases() as $type) {
            $field = $type->column();
            $old = $before[$field]?->format('Y-m-d H:i:s');
            $new = $after[$field]?->format('Y-m-d H:i:s');

            if ($old !== $new) {
                $changes[] = [
                    'field' => $field,
                    'attendance_type' => $type->value,
                    'original' => $old,
                    'new' => $new,
                ];
            }
        }

        return $changes;
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
            ->get([
                'id',
                'employee_id',
                'attendance_date',
                'am_time_in',
                'am_time_out',
                'pm_time_in',
                'pm_time_out',
                'overtime_in',
                'time_in',
                'time_out',
                'total_minutes',
                'late_minutes',
                'undertime_minutes',
                'overtime_minutes',
                'status',
            ])
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
            $flags['completed'] = $row->isRegularComplete();

            if (! $row->isRegularComplete()) {
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

    private function pendingCorrectionMessage(Employee $employee, string $date): ?string
    {
        $pending = AttendanceCorrectionRequest::query()
            ->where('employee_id', $employee->id)
            ->forDate($date)
            ->pending()
            ->select(['id', 'punch_type'])
            ->first();

        if (! $pending) {
            return null;
        }

        return 'You have a pending DTR correction request for '.$pending->punchLabel().' on '.$date.'. Please wait for admin review.';
    }

    private function stationResult(
        string $code,
        bool $recorded,
        Attendance $attendance,
        Employee $employee,
        Carbon $now,
        $schedule,
        ?AttendancePunchType $recordedType,
        ?string $customMessage = null
    ): array {
        $next = $this->sequence->nextExpected($recorded ? $attendance : null);

        return [
            'code' => $code,
            'recorded' => $recorded,
            'action' => $recordedType?->scanCode(),
            'action_label' => $recordedType?->label(),
            'next_action' => $next?->scanCode(),
            'next_action_label' => $next?->label(),
            'punch_type' => $recordedType?->value,
            'attendance' => $attendance,
            'employee' => $employee,
            'recorded_at' => $now,
            'schedule' => $schedule,
            'message' => $customMessage,
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

    public function flagPreviousIncompleteFor(Employee $employee, string $today): void
    {
        $previous = Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('attendance_date', '<', $today)
            ->where(function ($q) {
                $q->whereNotNull('am_time_in')->whereNull('pm_time_out')
                    ->orWhere(fn ($q2) => $q2->whereNotNull('time_in')->whereNull('time_out'));
            })
            ->orderByDesc('attendance_date')
            ->first();

        if (! $previous) {
            return;
        }

        $this->notifications->notifyAdmins(
            'Incomplete DTR',
            "{$employee->fullName()} has incomplete attendance on {$previous->attendance_date->toFormattedDateString()}.",
            'danger',
            route('admin.dtr.index', ['date' => $previous->attendance_date->toDateString()])
        );

        if ($employee->user) {
            $this->notifications->notify(
                $employee->user,
                'Incomplete DTR',
                'You have incomplete attendance on '.$previous->attendance_date->toFormattedDateString().'. Please contact your administrator.',
                'warning',
                route('employee.dtr')
            );
        }
    }
}
