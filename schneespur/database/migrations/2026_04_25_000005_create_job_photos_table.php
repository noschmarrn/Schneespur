<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
            $table->string('file_path', 500);
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('caption', 500)->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('job_id');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_photos');
    }
};
