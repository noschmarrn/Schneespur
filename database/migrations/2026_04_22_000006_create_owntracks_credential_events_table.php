<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owntracks_credential_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->string('event', 16);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['driver_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owntracks_credential_events');
    }
};
