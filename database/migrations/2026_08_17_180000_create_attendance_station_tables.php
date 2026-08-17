<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_stations', function (Blueprint $table) {
            $table->id();
            $table->string('station_code')->unique();
            $table->string('station_name');
            $table->string('password');
            $table->string('location');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('device_status', 32)->default('unbound');
            $table->string('binding_nonce', 64)->nullable();
            $table->unsignedSmallInteger('idle_timeout_minutes')->default(0);
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestamp('login_locked_until')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_scan_at')->nullable();
            $table->rememberToken();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'device_status']);
            $table->index('last_seen_at');
        });

        Schema::create('station_device_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_station_id')->constrained('attendance_stations')->cascadeOnDelete();
            $table->string('device_identifier_hash', 64);
            $table->string('binding_token_hash', 64);
            $table->timestamp('bound_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('unbound_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->index(['attendance_station_id', 'status']);
            $table->index('device_identifier_hash');
            $table->index('binding_token_hash');
        });

        Schema::create('station_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_station_id')->nullable()->constrained('attendance_stations')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('action', 64);
            $table->string('result', 32);
            $table->string('failure_reason', 128)->nullable();
            $table->text('message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_identifier_hash', 64)->nullable();
            $table->timestamp('scanned_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['attendance_station_id', 'scanned_at']);
            $table->index(['employee_id', 'scanned_at']);
            $table->index('action');
        });

        Schema::create('employee_qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->string('status', 32)->default('active');
            $table->timestamp('generated_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->foreignId('time_in_station_id')->nullable()->after('time_in')->constrained('attendance_stations')->nullOnDelete();
            $table->string('time_in_station_name')->nullable()->after('time_in_station_id');
            $table->string('time_in_station_location')->nullable()->after('time_in_station_name');
            $table->foreignId('time_out_station_id')->nullable()->after('time_out')->constrained('attendance_stations')->nullOnDelete();
            $table->string('time_out_station_name')->nullable()->after('time_out_station_id');
            $table->string('time_out_station_location')->nullable()->after('time_out_station_name');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropConstrainedForeignId('time_in_station_id');
            $table->dropConstrainedForeignId('time_out_station_id');
            $table->dropColumn([
                'time_in_station_name',
                'time_in_station_location',
                'time_out_station_name',
                'time_out_station_location',
            ]);
        });

        Schema::dropIfExists('employee_qr_tokens');
        Schema::dropIfExists('station_activity_logs');
        Schema::dropIfExists('station_device_bindings');
        Schema::dropIfExists('attendance_stations');
    }
};
