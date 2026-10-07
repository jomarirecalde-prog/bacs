<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_employees', function (Blueprint $table) {
            $table->decimal('computed_total_basic_pay', 12, 2)->default(0)->after('total_basic_pay');
            $table->boolean('total_basic_pay_manually_set')->default(false)->after('computed_total_basic_pay');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_employees', function (Blueprint $table) {
            $table->dropColumn(['computed_total_basic_pay', 'total_basic_pay_manually_set']);
        });
    }
};
