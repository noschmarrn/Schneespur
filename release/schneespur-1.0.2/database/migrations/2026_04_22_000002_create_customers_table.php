<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('street', 200)->nullable();
            $table->string('zip', 16)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('contact_name', 200)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('phone', 50)->nullable();
            $table->unsignedInteger('price_amount_cents')->nullable();
            $table->string('price_unit', 32)->nullable();
            $table->text('site_notes')->nullable();
            $table->unsignedTinyInteger('plow_threshold_cm')->nullable();
            $table->boolean('salt_enabled')->default(false);
            $table->text('access_notes')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->boolean('auto_notify_email')->default(false);
            $table->string('notification_email', 200)->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
