<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('event_type', 32);
            $table->text('description')->nullable();

            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_all_day')->default(true);

            $table->string('location')->nullable();
            $table->text('additional_instructions')->nullable();

            $table->string('audience_type', 32)->default('all');
            $table->string('attendance_effect', 32)->nullable();
            $table->boolean('notify_audience')->default(false);
            $table->timestamp('notified_at')->nullable();

            $table->string('status', 32)->default('published');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Range overlap lookups drive every calendar view and the holiday resolver.
            $table->index(['start_date', 'end_date']);
            $table->index(['status', 'start_date']);
            $table->index(['event_type', 'start_date']);
            $table->index(['attendance_effect', 'start_date']);
        });

        Schema::create('calendar_event_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();

            $table->unique(['calendar_event_id', 'department_id'], 'calendar_event_department_unique');
            $table->index('department_id');
        });

        Schema::create('calendar_event_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->unique(['calendar_event_id', 'employee_id'], 'calendar_event_employee_unique');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_employee');
        Schema::dropIfExists('calendar_event_department');
        Schema::dropIfExists('calendar_events');
    }
};
