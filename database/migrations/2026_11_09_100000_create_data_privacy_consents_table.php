<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_privacy_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('notice_version', 32);
            $table->string('action', 16);
            $table->timestampTz('consented_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('notice_text_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'notice_version', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_privacy_consents');
    }
};
