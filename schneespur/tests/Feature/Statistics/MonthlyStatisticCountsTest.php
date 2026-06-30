<?php

namespace Tests\Feature\Statistics;

use App\Models\MonthlyStatistic;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonthlyStatisticCountsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_counts_column_casts_to_array(): void
    {
        $stat = MonthlyStatistic::create([
            'year' => 2026,
            'month' => 1,
            'total_jobs' => 7,
            'counts' => ['raumen' => 5, 'maehen' => 2],
            'manual_count' => 0,
        ]);

        $this->assertSame(['raumen' => 5, 'maehen' => 2], $stat->fresh()->counts);
    }

    public function test_migration_backfills_counts_from_legacy_columns(): void
    {
        // Under LazilyRefreshDatabase all migrations have already run, so the
        // *_count columns are gone and `counts` already exists.  Drive the
        // migration object directly to recreate the old schema, seed a legacy
        // row, then re-run the migration and assert the backfill.
        $migration = require database_path('migrations/2026_06_29_000001_add_counts_to_monthly_statistics.php');

        // Re-adds raumen_count / streuen_count / kontrolle_count / raumen_streuen_count,
        // then drops counts — restoring the old schema.
        $migration->down();

        // Insert a pre-existing old-schema row (all NOT NULL columns have defaults,
        // year and month are required; everything else defaults to 0).
        DB::table('monthly_statistics')->insert([
            'year'               => 2025,
            'month'              => 1,
            'total_jobs'         => 3,
            'raumen_count'       => 3,
            'streuen_count'      => 1,
            'kontrolle_count'    => 0,
            'raumen_streuen_count' => 0,
            'manual_count'       => 0,
        ]);

        // Run the migration: backfills counts from the *_count columns, drops them.
        $migration->up();

        $row = MonthlyStatistic::where('year', 2025)->where('month', 1)->first();
        $this->assertNotNull($row, 'backfilled row should exist');
        $this->assertSame(3, $row->counts['raumen']);
        $this->assertSame(1, $row->counts['streuen']);
        $this->assertSame(0, $row->counts['kontrolle']);
        $this->assertSame(0, $row->counts['raumen_streuen']);
    }
}
