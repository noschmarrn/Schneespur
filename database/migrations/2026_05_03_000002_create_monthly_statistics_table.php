<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('total_jobs')->default(0);
            $table->unsignedInteger('raumen_count')->default(0);
            $table->unsignedInteger('streuen_count')->default(0);
            $table->unsignedInteger('kontrolle_count')->default(0);
            $table->unsignedInteger('raumen_streuen_count')->default(0);
            $table->unsignedInteger('manual_count')->default(0);
            $table->unsignedInteger('total_gps_points')->default(0);
            $table->unsignedInteger('total_photos')->default(0);
            $table->unsignedInteger('total_duration_minutes')->default(0);
            $table->decimal('avg_temperature', 5, 2)->nullable();
            $table->unsignedInteger('unique_customers')->default(0);
            $table->unsignedInteger('unique_drivers')->default(0);
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_statistics');
    }
};
