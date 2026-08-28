<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\DtrDayPresenter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dateTime('am_time_in')->nullable()->after('time_in_station_location');
            $table->dateTime('am_time_out')->nullable()->after('am_time_in');
            $table->dateTime('pm_time_in')->nullable()->after('am_time_out');
            $table->dateTime('pm_time_out')->nullable()->after('pm_time_in');
            $table->dateTime('overtime_in')->nullable()->after('pm_time_out');
            $table->json('punch_stations')->nullable()->after('overtime_in');
        });

        Schema::table('attendance_edits', function (Blueprint $table) {
            $table->json('field_changes')->nullable()->after('new_status');
        });

        $this->backfillExistingRecords();
    }

    public function down(): void
    {
        Schema::table('attendance_edits', function (Blueprint $table) {
            $table->dropColumn('field_changes');
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn([
                'am_time_in',
                'am_time_out',
                'pm_time_in',
                'pm_time_out',
                'overtime_in',
                'punch_stations',
            ]);
        });
    }

    private function backfillExistingRecords(): void
    {
        $presenter = app(DtrDayPresenter::class);

        Attendance::query()
            ->where(function ($q) {
                $q->whereNotNull('time_in')->orWhereNotNull('time_out');
            })
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($presenter) {
                foreach ($records as $record) {
                    if ($record->am_time_in || $record->pm_time_in) {
                        continue;
                    }

                    $employee = Employee::query()->with('workSchedule')->find($record->employee_id);
                    if (! $employee) {
                        continue;
                    }

                    $date = $record->attendance_date->toDateString();
                    [$amIn, $amOut, $pmIn, $pmOut] = $presenter->splitPunches(
                        $date,
                        $record->time_in,
                        $record->time_out,
                        $employee->schedule()
                    );

                    $record->update([
                        'am_time_in' => $amIn,
                        'am_time_out' => $amOut,
                        'pm_time_in' => $pmIn,
                        'pm_time_out' => $pmOut,
                    ]);
                }
            });
    }
};
