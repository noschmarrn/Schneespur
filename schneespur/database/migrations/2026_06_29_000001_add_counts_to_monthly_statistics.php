<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_statistics', function (Blueprint $table) {
            $table->json('counts')->nullable()->after('total_jobs');
        });

        // Backfill existing rows from the fixed per-type columns into the JSON map.
        foreach (DB::table('monthly_statistics')->get() as $row) {
            DB::table('monthly_statistics')->where('id', $row->id)->update([
                'counts' => json_encode([
                    'raumen' => (int) $row->raumen_count,
                    'streuen' => (int) $row->streuen_count,
                    'kontrolle' => (int) $row->kontrolle_count,
                    'raumen_streuen' => (int) $row->raumen_streuen_count,
                ]),
            ]);
        }

        Schema::table('monthly_statistics', function (Blueprint $table) {
            $table->dropColumn(['raumen_count', 'streuen_count', 'kontrolle_count', 'raumen_streuen_count']);
        });
    }

    public function down(): void
    {
        Schema::table('monthly_statistics', function (Blueprint $table) {
            $table->unsignedInteger('raumen_count')->default(0)->after('total_jobs');
            $table->unsignedInteger('streuen_count')->default(0)->after('raumen_count');
            $table->unsignedInteger('kontrolle_count')->default(0)->after('streuen_count');
            $table->unsignedInteger('raumen_streuen_count')->default(0)->after('kontrolle_count');
        });

        foreach (DB::table('monthly_statistics')->get() as $row) {
            $counts = json_decode($row->counts ?? '{}', true) ?: [];
            DB::table('monthly_statistics')->where('id', $row->id)->update([
                'raumen_count' => (int) ($counts['raumen'] ?? 0),
                'streuen_count' => (int) ($counts['streuen'] ?? 0),
                'kontrolle_count' => (int) ($counts['kontrolle'] ?? 0),
                'raumen_streuen_count' => (int) ($counts['raumen_streuen'] ?? 0),
            ]);
        }

        Schema::table('monthly_statistics', function (Blueprint $table) {
            $table->dropColumn('counts');
        });
    }
};
