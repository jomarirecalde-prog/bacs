<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('official_time_requests', function (Blueprint $table) {
            $table->time('am_time_in')->nullable()->after('time_to');
            $table->time('am_time_out')->nullable()->after('am_time_in');
            $table->time('pm_time_in')->nullable()->after('am_time_out');
            $table->time('pm_time_out')->nullable()->after('pm_time_in');
        });

        DB::table('official_time_requests')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('official_time_requests')->where('id', $row->id)->update([
                    'am_time_in' => $row->time_from,
                    'pm_time_out' => $row->time_to,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('official_time_requests', function (Blueprint $table) {
            $table->dropColumn(['am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out']);
        });
    }
};
