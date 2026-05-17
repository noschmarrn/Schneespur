<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weather_snapshots', function (Blueprint $table) {
            $table->string('provider', 32)->nullable()->after('moment');
            $table->decimal('wind_speed', 5, 2)->nullable()->after('snow_depth');
            $table->unsignedSmallInteger('humidity')->nullable()->after('wind_speed');
        });
    }

    public function down(): void
    {
        Schema::table('weather_snapshots', function (Blueprint $table) {
            $table->dropColumn(['provider', 'wind_speed', 'humidity']);
        });
    }
};
