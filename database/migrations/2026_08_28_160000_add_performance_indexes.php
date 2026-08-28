<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'app_notifications_user_created_index');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->index(['employee_id', 'status', 'start_date', 'end_date'], 'leaves_employee_status_range_index');
        });
    }

    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropIndex('app_notifications_user_created_index');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropIndex('leaves_employee_status_range_index');
        });
    }
};
