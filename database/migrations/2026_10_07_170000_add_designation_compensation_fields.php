<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->decimal('default_semi_monthly_salary', 12, 2)->nullable()->after('default_basic_salary');
            $table->unsignedSmallInteger('default_working_days_per_period')->nullable()->after('default_working_hours_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->dropColumn(['default_semi_monthly_salary', 'default_working_days_per_period']);
        });
    }
};
