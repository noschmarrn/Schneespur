<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('module_slug', 128);
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('module_slug')
                ->references('slug')
                ->on('modules')
                ->cascadeOnDelete();

            $table->index('token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_api_tokens');
    }
};
