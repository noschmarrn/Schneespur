<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('driver')->after('password');
            $table->timestamp('dsgvo_informed_at')->nullable()->after('role');
            $table->unsignedInteger('confirmed_version')->nullable()->after('dsgvo_informed_at');
            $table->timestamp('anonymized_at')->nullable()->after('confirmed_version');
            $table->text('anonymization_reason')->nullable()->after('anonymized_at');
            $table->string('owntracks_username', 64)->nullable()->unique()->after('anonymization_reason');
            $table->string('owntracks_password_hash', 255)->nullable()->after('owntracks_username');
            $table->timestamp('owntracks_password_revealed_at')->nullable()->after('owntracks_password_hash');

            $table->index(['role', 'anonymized_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'anonymized_at']);

            $table->dropColumn([
                'role',
                'dsgvo_informed_at',
                'confirmed_version',
                'anonymized_at',
                'anonymization_reason',
                'owntracks_username',
                'owntracks_password_hash',
                'owntracks_password_revealed_at',
            ]);
        });
    }
};
