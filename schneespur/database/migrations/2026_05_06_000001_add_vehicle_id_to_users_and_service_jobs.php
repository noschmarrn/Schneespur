<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_vehicle_id')
                ->nullable()
                ->after('notes')
                ->constrained('vehicles')
                ->nullOnDelete();
        });

        Schema::table('service_jobs', function (Blueprint $table) {
            $table->foreignId('vehicle_id')
                ->nullable()
                ->after('user_id')
                ->constrained('vehicles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_vehicle_id');
        });
    }
};
