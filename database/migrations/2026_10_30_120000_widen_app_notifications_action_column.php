<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_notifications') || ! Schema::hasColumn('app_notifications', 'action')) {
            return;
        }

        DB::statement('ALTER TABLE `app_notifications` MODIFY `action` VARCHAR(64) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('app_notifications') || ! Schema::hasColumn('app_notifications', 'action')) {
            return;
        }

        DB::statement('ALTER TABLE `app_notifications` MODIFY `action` VARCHAR(32) NULL');
    }
};
