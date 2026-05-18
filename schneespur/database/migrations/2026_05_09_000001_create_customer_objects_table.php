<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('street', 200)->nullable();
            $table->string('zip', 16)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->string('contact_name', 200)->nullable();
            $table->string('contact_email', 200)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->unsignedInteger('price_amount_cents')->nullable();
            $table->string('price_unit', 32)->nullable();
            $table->unsignedTinyInteger('plow_threshold_cm')->nullable();
            $table->boolean('salt_enabled')->default(false);
            $table->text('site_notes')->nullable();
            $table->text('access_notes')->nullable();
            $table->string('notify_recipients', 32)->default('customer');
            $table->boolean('auto_notify_email')->default(false);
            $table->string('notification_email', 200)->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_objects');
    }
};
