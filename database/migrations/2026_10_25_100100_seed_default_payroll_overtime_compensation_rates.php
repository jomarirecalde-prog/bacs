<?php

use App\Services\Payroll\PayrollOvertimeCompensationDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_overtime_compensation_rates')) {
            return;
        }

        if (DB::table('payroll_overtime_compensation_rates')->exists()) {
            return;
        }

        $now = now();
        $effectiveFrom = '2020-01-01';

        foreach (PayrollOvertimeCompensationDefaults::rows() as $row) {
            DB::table('payroll_overtime_compensation_rates')->insert([
                'condition_code' => $row['condition_code'],
                'label' => $row['label'],
                'workday_classification' => $row['workday_classification']->value,
                'is_rest_day' => $row['is_rest_day'],
                'is_night_shift' => $row['is_night_shift'],
                'is_overtime' => $row['is_overtime'],
                'multiplier' => $row['multiplier'],
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => true,
                'remarks' => 'Seeded default OT compensation reference.',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_overtime_compensation_rates')) {
            return;
        }

        $codes = array_column(PayrollOvertimeCompensationDefaults::rows(), 'condition_code');
        DB::table('payroll_overtime_compensation_rates')->whereIn('condition_code', $codes)->delete();
    }
};
