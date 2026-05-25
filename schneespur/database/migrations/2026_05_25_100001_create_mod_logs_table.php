<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module_slug', 128)->index();
            $table->string('level', 16);
            $table->string('message', 512);
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_logs');
    }
};
