<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('designation_code', 64)->unique();
            $table->string('designation_name')->unique();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('default_pay_type', 32)->nullable();
            $table->decimal('default_basic_salary', 12, 2)->nullable();
            $table->decimal('default_daily_rate', 12, 2)->nullable();
            $table->decimal('default_hourly_rate', 12, 2)->nullable();
            $table->unsignedSmallInteger('default_working_hours_per_day')->default(8);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'designation_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};
