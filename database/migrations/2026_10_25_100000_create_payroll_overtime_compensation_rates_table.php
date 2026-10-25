<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_overtime_compensation_rates', function (Blueprint $table) {
            $table->id();
            $table->string('condition_code', 64)->nullable()->index();
            $table->string('label');
            $table->string('workday_classification', 48);
            $table->boolean('is_rest_day')->default(false);
            $table->boolean('is_night_shift')->default(false);
            $table->boolean('is_overtime')->default(true);
            $table->decimal('multiplier', 10, 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['workday_classification', 'is_rest_day', 'is_night_shift', 'is_overtime', 'effective_from'],
                'ot_comp_rate_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_overtime_compensation_rates');
    }
};
