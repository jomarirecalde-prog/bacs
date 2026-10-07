<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('central_approval_config_id')->nullable()->after('status');
            $table->unsignedInteger('central_approval_config_version')->nullable()->after('central_approval_config_id');
            $table->string('current_approval_stage', 32)->nullable()->after('central_approval_config_version');

            $table->foreign('central_approval_config_id', 'ot_req_awf_config_fk')
                ->references('id')->on('approval_workflow_configurations')->nullOnDelete();
        });

        Schema::create('overtime_approval_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('overtime_request_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 32);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('approver_name');
            $table->string('approver_position')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['overtime_request_id', 'stage', 'user_id'], 'ot_assign_unique');
            $table->index(['user_id', 'status']);
        });

        if (! DB::table('approval_workflow_configurations')->where('transaction_type', 'overtime_request')->exists()) {
            DB::table('approval_workflow_configurations')->insert([
                'transaction_type' => 'overtime_request',
                'endorsement_enabled' => true,
                'final_approval_enabled' => true,
                'version' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::create('payroll_tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->decimal('compensation_min', 12, 2);
            $table->decimal('compensation_max', 12, 2)->nullable();
            $table->decimal('base_tax', 12, 2)->default(0);
            $table->decimal('rate_on_excess', 8, 4)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['effective_from', 'sort_order']);
        });

        $now = now();
        $effective = '2025-01-01';
        $taxRows = [
            [0, 20833, 0, 0],
            [20833.01, 33332, 0, 0.15],
            [33333, 66666, 1875, 0.20],
            [66667, 166666, 8541.80, 0.25],
            [166667, 666666, 33541.80, 0.30],
            [666667, null, 183541.80, 0.35],
        ];

        foreach ($taxRows as $i => [$min, $max, $base, $rate]) {
            DB::table('payroll_tax_brackets')->insert([
                'effective_from' => $effective,
                'compensation_min' => $min,
                'compensation_max' => $max,
                'base_tax' => $base,
                'rate_on_excess' => $rate,
                'sort_order' => ($i + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('payroll_premium_rules')->where('scenario_code', 'holiday_overtime')->exists()) {
            DB::table('payroll_premium_rules')->insert([
                'scenario_code' => 'holiday_overtime',
                'name' => 'Overtime on holiday',
                'pay_component' => 'premium_pay',
                'multiplier' => 2.0,
                'holiday_type' => null,
                'description' => 'Additional premium for OT hours rendered on a holiday (multiplier − 1 × OT hourly).',
                'is_active' => true,
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('payroll_premium_rules')->where('scenario_code', 'regular_holiday_unworked')->exists()) {
            DB::table('payroll_premium_rules')->insert([
                'scenario_code' => 'regular_holiday_unworked',
                'name' => 'Regular holiday (unworked, eligible)',
                'pay_component' => 'holiday_pay',
                'multiplier' => 1.0,
                'holiday_type' => 'regular',
                'description' => 'Daily rate for eligible employees on unworked regular holidays when enabled in payroll settings.',
                'is_active' => false,
                'sort_order' => 50,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_tax_brackets');
        Schema::dropIfExists('overtime_approval_assignments');

        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('central_approval_config_id');
            $table->dropColumn(['central_approval_config_version', 'current_approval_stage']);
        });

        DB::table('approval_workflow_configurations')->where('transaction_type', 'overtime_request')->delete();
        DB::table('payroll_premium_rules')->whereIn('scenario_code', ['holiday_overtime', 'regular_holiday_unworked'])->delete();
    }
};
