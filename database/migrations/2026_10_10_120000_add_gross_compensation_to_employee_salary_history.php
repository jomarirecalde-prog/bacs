<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_salary_history', function (Blueprint $table) {
            $table->decimal('gross_compensation', 12, 2)->nullable()->after('basic_salary');
        });
    }

    public function down(): void
    {
        Schema::table('employee_salary_history', function (Blueprint $table) {
            $table->dropColumn('gross_compensation');
        });
    }
};
