<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_dsgvo_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->useCurrent();
            $table->string('signed_by', 200);
            $table->longText('notice_text_snapshot');
            $table->string('notice_language', 8)->default('de');
            $table->unsignedInteger('template_version')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('app_version', 32)->nullable();

            $table->index(['driver_id', 'confirmed_at']);
            $table->index('template_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_dsgvo_confirmations');
    }
};
