<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->foreignId('calendar_event_id')->nullable()->after('link')->constrained('calendar_events')->nullOnDelete();
            $table->string('action', 32)->nullable()->after('calendar_event_id');

            $table->index(['user_id', 'calendar_event_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'calendar_event_id', 'action']);
            $table->dropConstrainedForeignId('calendar_event_id');
            $table->dropColumn('action');
        });
    }
};
