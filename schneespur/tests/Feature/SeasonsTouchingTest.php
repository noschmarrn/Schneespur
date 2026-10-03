<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SeasonService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SeasonsTouchingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function touching(string $from, string $to): array
    {
        return array_map(
            fn (object $s) => $s->start->format('Y-m-d').'..'.$s->end->format('Y-m-d'),
            app(SeasonService::class)->seasonsTouching(Carbon::parse($from), Carbon::parse($to)),
        );
    }

    private function winterSeason(): void
    {
        Setting::set('season_from', '11-01');
        Setting::set('season_to', '03-31');
    }

    public function test_a_period_inside_one_winter_touches_that_season(): void
    {
        $this->winterSeason();

        $this->assertSame(['2025-11-01..2026-03-31'], $this->touching('2026-01-01', '2026-01-31'));
    }

    public function test_a_period_across_new_year_still_touches_one_season(): void
    {
        $this->winterSeason();

        $this->assertSame(['2025-11-01..2026-03-31'], $this->touching('2025-12-15', '2026-01-15'));
    }

    public function test_a_summer_period_touches_no_season(): void
    {
        $this->winterSeason();

        $this->assertSame([], $this->touching('2026-06-01', '2026-08-31'));
    }

    public function test_a_whole_calendar_year_touches_two_winters(): void
    {
        $this->winterSeason();

        $this->assertSame(
            ['2025-11-01..2026-03-31', '2026-11-01..2027-03-31'],
            $this->touching('2026-01-01', '2026-12-31'),
        );
    }

    public function test_the_last_day_of_the_season_counts(): void
    {
        $this->winterSeason();

        $this->assertSame(['2025-11-01..2026-03-31'], $this->touching('2026-03-31', '2026-04-30'));
        $this->assertSame([], $this->touching('2026-04-01', '2026-04-30'));
    }

    public function test_a_season_inside_one_year(): void
    {
        Setting::set('season_from', '01-01');
        Setting::set('season_to', '04-30');

        $this->assertSame(['2026-01-01..2026-04-30'], $this->touching('2026-03-01', '2026-06-30'));
    }
}
