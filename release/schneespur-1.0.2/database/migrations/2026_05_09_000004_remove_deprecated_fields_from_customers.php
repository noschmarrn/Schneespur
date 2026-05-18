<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['city']);

            $table->dropColumn([
                'street',
                'zip',
                'city',
                'lat',
                'lon',
                'price_amount_cents',
                'price_unit',
                'site_notes',
                'plow_threshold_cm',
                'salt_enabled',
                'access_notes',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('street', 200)->nullable()->after('name');
            $table->string('zip', 16)->nullable()->after('street');
            $table->string('city', 100)->nullable()->after('zip');
            $table->unsignedInteger('price_amount_cents')->nullable()->after('phone');
            $table->string('price_unit', 32)->nullable()->after('price_amount_cents');
            $table->text('site_notes')->nullable()->after('price_unit');
            $table->unsignedTinyInteger('plow_threshold_cm')->nullable()->after('site_notes');
            $table->boolean('salt_enabled')->default(false)->after('plow_threshold_cm');
            $table->text('access_notes')->nullable()->after('salt_enabled');
            $table->decimal('lat', 10, 7)->nullable()->after('access_notes');
            $table->decimal('lon', 10, 7)->nullable()->after('lat');

            $table->index('city');
        });
    }
};
