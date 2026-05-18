<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_object_id')->nullable()->after('customer_id');
        });

        DB::statement('
            UPDATE service_jobs
            SET customer_object_id = (
                SELECT co.id
                FROM customer_objects co
                WHERE co.customer_id = service_jobs.customer_id
                LIMIT 1
            )
        ');

        Schema::table('service_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_object_id')->nullable(false)->change();
            $table->foreign('customer_object_id')
                ->references('id')
                ->on('customer_objects')
                ->restrictOnDelete();
            $table->index('customer_object_id');
        });
    }

    public function down(): void
    {
        Schema::table('service_jobs', function (Blueprint $table) {
            $table->dropForeign(['customer_object_id']);
            $table->dropIndex(['customer_object_id']);
            $table->dropColumn('customer_object_id');
        });
    }
};
