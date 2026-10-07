<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_premium_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_code', 64)->unique();
            $table->string('name');
            $table->string('pay_component', 32)->default('holiday_pay');
            $table->decimal('multiplier', 8, 4)->default(1);
            $table->string('holiday_type', 32)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $rows = [
            [
                'scenario_code' => 'regular_holiday_work',
                'name' => 'Regular Holiday (work performed)',
                'pay_component' => 'holiday_pay',
                'multiplier' => 2.0,
                'holiday_type' => 'regular',
                'description' => 'Default 200% of hourly rate for work on regular holidays. Adjust per company policy.',
            ],
            [
                'scenario_code' => 'special_holiday_work',
                'name' => 'Special Holiday (work performed)',
                'pay_component' => 'holiday_pay',
                'multiplier' => 1.3,
                'holiday_type' => 'special',
                'description' => 'Default 130% of hourly rate for work on special non-working holidays.',
            ],
            [
                'scenario_code' => 'rest_day_work',
                'name' => 'Rest Day (work performed)',
                'pay_component' => 'premium_pay',
                'multiplier' => 1.3,
                'holiday_type' => null,
                'description' => 'Premium for work performed on scheduled rest day.',
            ],
        ];

        foreach ($rows as $i => $row) {
            DB::table('payroll_premium_rules')->insert($row + [
                'is_active' => true,
                'sort_order' => ($i + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_premium_rules');
    }
};
