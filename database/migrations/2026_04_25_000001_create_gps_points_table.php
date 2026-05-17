<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('job_id')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lon', 10, 7);
            $table->unsignedInteger('timestamp');
            $table->decimal('altitude', 8, 2)->nullable();
            $table->unsignedTinyInteger('battery')->nullable();
            $table->unsignedInteger('velocity')->nullable();
            $table->unsignedInteger('accuracy')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('job_id');
            $table->index('timestamp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_points');
    }
};
