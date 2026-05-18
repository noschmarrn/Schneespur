<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
            $table->string('moment', 8);
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('precipitation', 6, 2)->nullable();
            $table->decimal('snow_depth', 6, 2)->nullable();
            $table->unsignedSmallInteger('weather_code')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique(['job_id', 'moment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_snapshots');
    }
};
