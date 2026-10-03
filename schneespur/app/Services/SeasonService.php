<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;

class SeasonService
{
    public function currentOrLastSeason(?Carbon $now = null): object
    {
        $now = $now ?? Carbon::now();

        [$startMonth, $startDay, $endMonth, $endDay, $crossesYear] = $this->bounds();

        if ($crossesYear) {
            return $this->resolveYearCrossingSeason($now, $startMonth, $startDay, $endMonth, $endDay);
        }

        return $this->resolveSameYearSeason($now, $startMonth, $startDay, $endMonth, $endDay);
    }

    /**
     * Alle Saisons, die den Zeitraum berühren — aufsteigend. Eine Saison
     * über den Jahreswechsel zählt einmal, auch wenn der Zeitraum beide
     * Kalenderjahre anschneidet.
     *
     * @return array<int, object{start: Carbon, end: Carbon}>
     */
    public function seasonsTouching(Carbon $from, Carbon $to): array
    {
        [$startMonth, $startDay, $endMonth, $endDay, $crossesYear] = $this->bounds();

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $seasons = [];

        for ($year = $from->year - 1; $year <= $to->year; $year++) {
            $start = Carbon::create($year, $startMonth, $startDay)->startOfDay();
            $end = Carbon::create($crossesYear ? $year + 1 : $year, $endMonth, $endDay)->endOfDay();

            if ($start->lessThanOrEqualTo($to) && $end->greaterThanOrEqualTo($from)) {
                $seasons[] = (object) ['start' => $start, 'end' => $end];
            }
        }

        return $seasons;
    }

    /**
     * @return array{int, int, int, int, bool}
     */
    private function bounds(): array
    {
        [$startMonth, $startDay] = array_map('intval', explode('-', (string) Setting::get('season_from', '11-01')));
        [$endMonth, $endDay] = array_map('intval', explode('-', (string) Setting::get('season_to', '03-31')));

        $crossesYear = $startMonth > $endMonth
            || ($startMonth === $endMonth && $startDay > $endDay);

        return [$startMonth, $startDay, $endMonth, $endDay, $crossesYear];
    }

    private function resolveYearCrossingSeason(
        Carbon $now,
        int $startMonth,
        int $startDay,
        int $endMonth,
        int $endDay,
    ): object {
        $year = $now->year;

        $candidateStartThisYear = Carbon::create($year, $startMonth, $startDay)->startOfDay();
        $candidateEndNextYear = Carbon::create($year + 1, $endMonth, $endDay)->endOfDay();

        if ($now->greaterThanOrEqualTo($candidateStartThisYear)) {
            return $this->makeSeason($candidateStartThisYear, $candidateEndNextYear, true);
        }

        $candidateEndThisYear = Carbon::create($year, $endMonth, $endDay)->endOfDay();

        if ($now->lessThanOrEqualTo($candidateEndThisYear)) {
            $start = Carbon::create($year - 1, $startMonth, $startDay)->startOfDay();
            return $this->makeSeason($start, $candidateEndThisYear, true);
        }

        $start = Carbon::create($year - 1, $startMonth, $startDay)->startOfDay();
        $end = Carbon::create($year, $endMonth, $endDay)->endOfDay();
        return $this->makeSeason($start, $end, false);
    }

    private function resolveSameYearSeason(
        Carbon $now,
        int $startMonth,
        int $startDay,
        int $endMonth,
        int $endDay,
    ): object {
        $year = $now->year;

        $start = Carbon::create($year, $startMonth, $startDay)->startOfDay();
        $end = Carbon::create($year, $endMonth, $endDay)->endOfDay();

        if ($now->greaterThanOrEqualTo($start) && $now->lessThanOrEqualTo($end)) {
            return $this->makeSeason($start, $end, true);
        }

        if ($now->lessThan($start)) {
            $prevStart = Carbon::create($year - 1, $startMonth, $startDay)->startOfDay();
            $prevEnd = Carbon::create($year - 1, $endMonth, $endDay)->endOfDay();
            return $this->makeSeason($prevStart, $prevEnd, false);
        }

        return $this->makeSeason($start, $end, false);
    }

    private function makeSeason(Carbon $start, Carbon $end, bool $isCurrent): object
    {
        $startLabel = $start->translatedFormat('M Y');
        $endLabel = $end->translatedFormat('M Y');
        $label = "{$startLabel} – {$endLabel}";

        return (object) [
            'start' => $start,
            'end' => $end,
            'isCurrent' => $isCurrent,
            'label' => $label,
        ];
    }
}
