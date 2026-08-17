<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('status');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('status');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('last_name');
            $table->index('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['full_name']);
            $table->dropColumn('full_name');
        });
    }
};
