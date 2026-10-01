<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\TravelOrderStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\TravelOrder;
use App\Models\TravelOrderDestination;
use App\Models\TravelOrderPersonnel;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendanceCalculator;
use App\Services\TravelOrderAttendanceService;
use App\Services\TravelOrderService;
use App\Support\ManilaTime;
use Illuminate\Database\Seeder;

/**
 * Demo attendance, leave, and travel orders for training and UAT.
 * Requires master data: php artisan db:seed
 */
class SampleDataSeeder extends Seeder
{
    public const SAMPLE_TO_APPROVED = 'TO-2026-900001';

    public const SAMPLE_TO_PENDING = 'TO-2026-900002';

    public function run(): void
    {
        if (Employee::query()->active()->count() < 10) {
            $this->command?->error('Master employee data is missing. Run: php artisan db:seed');

            return;
        }

        $admin = User::query()->where('username', 'admin')->first();
        $schedule = WorkSchedule::defaultSchedule();

        if (! $schedule) {
            $this->command?->error('Default work schedule is missing. Run: php artisan db:seed');

            return;
        }

        $this->seedRecentAttendance($schedule, $admin);
        $this->seedSampleLeave($admin);
        $this->seedApprovedTravelOrder($admin);
        $this->seedPendingTravelOrder();

        $this->command?->info('Sample data ready.');
        $this->command?->line('  • Approved travel '.self::SAMPLE_TO_APPROVED.' — travelers show TRAVEL on DTR for travel dates.');
        $this->command?->line('  • Pending travel '.self::SAMPLE_TO_PENDING.' — visible under Travel Order approvals.');
        $this->command?->line('  • Sample attendance for BACS-2026-0005 and BACS-2026-0007 (recent weekdays).');
        $this->command?->line('  • Sample approved leave for BACS-2026-0008 (next weekday).');
        $this->command?->line('  Log in: admin / password (or any BACS-2026-xxxx / password).');
    }

    private function seedRecentAttendance(WorkSchedule $schedule, ?User $admin): void
    {
        $calculator = app(AttendanceCalculator::class);
        $targets = [
            'BACS-2026-0005', // Gaid — Admin Assistant
            'BACS-2026-0007', // Acompañado — Finance
        ];

        foreach ($this->recentWorkdays(3) as $date) {
            foreach ($targets as $number) {
                $employee = $this->employee($number);
                if ($this->travelBlocks($employee, $date)) {
                    continue;
                }

                $punches = [
                    'am_time_in' => ManilaTime::combineDateAndTime($date, '08:02:00'),
                    'am_time_out' => ManilaTime::combineDateAndTime($date, '12:00:00'),
                    'pm_time_in' => ManilaTime::combineDateAndTime($date, '13:00:00'),
                    'pm_time_out' => ManilaTime::combineDateAndTime($date, '17:05:00'),
                    'overtime_in' => null,
                ];

                $computed = $calculator->calculateFromPunches($date, $punches, $schedule, $employee->id);

                $record = Attendance::query()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'attendance_date' => $date,
                    ],
                    array_merge($computed, $punches, [
                        'status' => $computed['status']->value,
                        'remarks' => 'Sample DTR (SampleDataSeeder)',
                        'is_manual' => true,
                        'created_by' => $admin?->id,
                    ])
                );
                $record->syncLegacyFields();
                $record->save();
            }
        }
    }

    private function seedSampleLeave(?User $admin): void
    {
        $employee = $this->employee('BACS-2026-0008'); // Consuelo
        $date = $this->nextWorkday(ManilaTime::todayDate());

        Leave::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'start_date' => $date,
                'end_date' => $date,
            ],
            [
                'type' => 'vacation',
                'status' => 'approved',
                'remarks' => 'Sample approved leave (SampleDataSeeder)',
                'created_by' => $admin?->id,
            ]
        );

        Attendance::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'attendance_date' => $date,
            ],
            [
                'status' => AttendanceStatus::OnLeave->value,
                'remarks' => 'Sample leave day',
                'total_minutes' => 0,
                'late_minutes' => 0,
                'undertime_minutes' => 0,
                'overtime_minutes' => 0,
            ]
        );
    }

    private function seedApprovedTravelOrder(?User $admin): void
    {
        $requester = $this->employee('BACS-2026-0014'); // Cayapas — Project Team Leader
        $travelers = [
            $this->employee('BACS-2026-0006'), // Lagrosa
            $this->employee('BACS-2026-0016'), // Morados
            $this->employee('BACS-2026-0028'), // Elevera
        ];

        $start = ManilaTime::todayDate();
        $end = ManilaTime::parse($start)->addDay()->toDateString();

        $order = TravelOrder::query()->updateOrCreate(
            ['travel_order_number' => self::SAMPLE_TO_APPROVED],
            [
                'requester_id' => $requester->id,
                'department_id' => $requester->department_id,
                'official_station' => 'BACS Main Office, Puerto Princesa',
                'destination' => 'Brooke\'s Point Site',
                'date_start' => $start,
                'date_end' => $end,
                'purpose' => 'Sample approved site inspection (SampleDataSeeder)',
                'transportation' => 'land',
                'vehicle_type' => 'Company pickup',
                'status' => TravelOrderStatus::Approved,
                'approved_at' => ManilaTime::now(),
                'submitted_at' => ManilaTime::now()->subDay(),
                'date_requested' => ManilaTime::now()->subDays(2),
                'submitted_by' => $requester->user_id,
                'finalized_by' => $admin?->id,
            ]
        );

        TravelOrderDestination::query()->updateOrCreate(
            ['travel_order_id' => $order->id, 'destination' => 'Brooke\'s Point Site'],
            ['sort_order' => 0]
        );

        foreach ($travelers as $traveler) {
            TravelOrderPersonnel::query()->firstOrCreate([
                'travel_order_id' => $order->id,
                'employee_id' => $traveler->id,
            ]);
        }

        app(TravelOrderAttendanceService::class)->applyApprovedOrder($order->fresh(['personnel']));
    }

    private function seedPendingTravelOrder(): void
    {
        if (TravelOrder::query()->where('travel_order_number', self::SAMPLE_TO_PENDING)->exists()) {
            return;
        }

        $requester = $this->employee('BACS-2026-0021'); // Fernandez — Junior Office Engineer
        $traveler = $this->employee('BACS-2026-0037'); // Bungay

        $start = ManilaTime::parse(ManilaTime::todayDate())->addWeek()->toDateString();
        $end = $start;

        $service = app(TravelOrderService::class);
        $order = $service->submit($requester, $requester->user, [
            'official_station' => 'BACS Main Office',
            'destinations' => ['El Nido Project'],
            'date_start' => $start,
            'date_end' => $end,
            'purpose' => 'Sample pending travel for endorsement (SampleDataSeeder)',
            'transportation' => 'land',
            'traveler_ids' => [$traveler->id],
        ]);
        $order->update(['travel_order_number' => self::SAMPLE_TO_PENDING]);
        $service->afterSubmit($order->fresh(['requester.user', 'assignments.user', 'personnel.employee.user']));
    }

    private function employee(string $number): Employee
    {
        $employee = Employee::query()->where('employee_number', $number)->with('user')->first();

        if (! $employee) {
            throw new \RuntimeException("Employee {$number} not found. Run DatabaseSeeder first.");
        }

        return $employee;
    }

    private function travelBlocks(Employee $employee, string $date): bool
    {
        return TravelOrder::query()
            ->where('status', TravelOrderStatus::Approved)
            ->where('date_start', '<=', $date)
            ->where('date_end', '>=', $date)
            ->whereHas('personnel', fn ($q) => $q->where('employee_id', $employee->id))
            ->exists();
    }

    /** @return list<string> */
    private function recentWorkdays(int $count): array
    {
        $days = [];
        $cursor = ManilaTime::today()->copy();

        while (count($days) < $count) {
            if ($cursor->isoWeekday() <= 5) {
                $days[] = $cursor->toDateString();
            }
            $cursor->subDay();
        }

        return $days;
    }

    private function nextWorkday(string $from): string
    {
        $cursor = ManilaTime::parse($from)->addDay();

        while ($cursor->isoWeekday() > 5) {
            $cursor->addDay();
        }

        return $cursor->toDateString();
    }
}
