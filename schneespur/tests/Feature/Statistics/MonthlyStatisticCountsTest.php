<?php

namespace Tests\Feature\Statistics;

use App\Models\MonthlyStatistic;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
}
