<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->rememberToken()->after('password');
            $table->boolean('portal_enabled')->default(false)->after('remember_token');
            $table->boolean('portal_show_gps')->default(true)->after('portal_enabled');
            $table->boolean('portal_show_photos')->default(true)->after('portal_show_gps');
            $table->boolean('portal_show_driver_name')->default(true)->after('portal_show_photos');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'password',
                'remember_token',
                'portal_enabled',
                'portal_show_gps',
                'portal_show_photos',
                'portal_show_driver_name',
            ]);
        });
    }
};
