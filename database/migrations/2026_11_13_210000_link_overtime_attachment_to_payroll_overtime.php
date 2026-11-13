<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_attachment_reports', function (Blueprint $table) {
            if (Schema::hasColumn('overtime_attachment_reports', 'official_time_request_id')) {
                $table->dropForeign(['official_time_request_id']);
                $table->dropUnique(['official_time_request_id']);
            }

            if (! Schema::hasColumn('overtime_attachment_reports', 'overtime_request_id')) {
                $table->foreignId('overtime_request_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('overtime_requests')
                    ->restrictOnDelete();
                $table->unique('overtime_request_id');
            }
        });

        if (Schema::hasColumn('overtime_attachment_reports', 'official_time_request_id')
            && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE overtime_attachment_reports MODIFY official_time_request_id BIGINT UNSIGNED NULL');
        }

        Schema::table('overtime_attachment_reports', function (Blueprint $table) {
            if (Schema::hasColumn('overtime_attachment_reports', 'official_time_request_id')) {
                $table->foreign('official_time_request_id')
                    ->references('id')->on('official_time_requests')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('overtime_attachment_reports', function (Blueprint $table) {
            if (Schema::hasColumn('overtime_attachment_reports', 'overtime_request_id')) {
                $table->dropConstrainedForeignId('overtime_request_id');
            }
        });
    }
};
