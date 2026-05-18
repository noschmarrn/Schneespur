<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_photos', function (Blueprint $table) {
            $table->string('annotated_path', 500)->nullable()->after('thumbnail_path');
        });
    }

    public function down(): void
    {
        Schema::table('job_photos', function (Blueprint $table) {
            $table->dropColumn('annotated_path');
        });
    }
};
