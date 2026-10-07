<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_sss_brackets', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->decimal('compensation_min', 12, 2);
            $table->decimal('compensation_max', 12, 2)->nullable();
            $table->decimal('monthly_salary_credit', 12, 2);
            $table->decimal('employee_share_monthly', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['effective_from', 'sort_order']);
        });

        $now = now();
        $effective = '2025-01-01';
        $rows = [
            [3250, 3749.99, 3500, 157.50],
            [3750, 4249.99, 4000, 180.00],
            [4250, 4749.99, 4500, 202.50],
            [4750, 5249.99, 5000, 225.00],
            [5250, 5749.99, 5500, 247.50],
            [5750, 6249.99, 6000, 270.00],
            [6250, 6749.99, 6500, 292.50],
            [6750, 7249.99, 7000, 315.00],
            [7250, 7749.99, 7500, 337.50],
            [7750, 8249.99, 8000, 360.00],
            [8250, 8749.99, 8500, 382.50],
            [8750, 9249.99, 9000, 405.00],
            [9250, 9749.99, 9500, 427.50],
            [9750, 10249.99, 10000, 450.00],
            [10250, 10749.99, 10500, 472.50],
            [10750, 11249.99, 11000, 495.00],
            [11250, 11749.99, 11500, 517.50],
            [11750, 12249.99, 12000, 540.00],
            [12250, 12749.99, 12500, 562.50],
            [12750, 13249.99, 13000, 585.00],
            [13250, 13749.99, 13500, 607.50],
            [13750, 14249.99, 14000, 630.00],
            [14250, 14749.99, 14500, 652.50],
            [14750, 15249.99, 15000, 675.00],
            [15250, 15749.99, 15500, 697.50],
            [15750, 16249.99, 16000, 720.00],
            [16250, 16749.99, 16500, 742.50],
            [16750, 17249.99, 17000, 765.00],
            [17250, 17749.99, 17500, 787.50],
            [17750, 18249.99, 18000, 810.00],
            [18250, 18749.99, 18500, 832.50],
            [18750, 19249.99, 19000, 855.00],
            [19250, 19749.99, 19500, 877.50],
            [19750, 20249.99, 20000, 900.00],
            [20250, 20749.99, 20500, 922.50],
            [20750, null, 21000, 945.00],
        ];

        foreach ($rows as $i => [$min, $max, $msc, $ee]) {
            DB::table('payroll_sss_brackets')->insert([
                'effective_from' => $effective,
                'compensation_min' => $min,
                'compensation_max' => $max,
                'monthly_salary_credit' => $msc,
                'employee_share_monthly' => $ee,
                'sort_order' => ($i + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });

        Schema::dropIfExists('payroll_sss_brackets');
    }
};
