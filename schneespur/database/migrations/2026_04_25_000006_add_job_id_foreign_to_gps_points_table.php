<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_points', function (Blueprint $table) {
            $table->foreign('job_id')->references('id')->on('service_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gps_points', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
        });
    }
};
