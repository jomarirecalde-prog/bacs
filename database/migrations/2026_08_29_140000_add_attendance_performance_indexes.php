<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            $table->index(
                ['employee_id', 'attendance_date', 'status'],
                'att_corr_emp_date_status_idx'
            );
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->index(['attendance_date', 'employee_id'], 'attendance_date_employee_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            $table->dropIndex('att_corr_emp_date_status_idx');
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropIndex('attendance_date_employee_idx');
        });
    }
};
