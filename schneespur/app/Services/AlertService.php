<?php

namespace App\Services;

use App\Enums\WeatherMoment;
use App\Models\AlertDismissal;
use App\Models\Job;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class AlertService
{
    public function missingGpsQuery(?Carbon $from = null, ?Carbon $to = null): Builder
    {
        $query = Job::query()
            ->whereNotNull('ended_at')
            ->where('is_manual', '!=', true)
            ->whereDoesntHave('gpsPoints')
            ->whereDoesntHave('alertDismissals', fn (Builder $q) => $q->where('alert_type', 'missing_gps'));

        if ($from) {
            $query->where('started_at', '>=', $from);
        }
        if ($to) {
            $query->where('started_at', '<=', $to);
        }

        return $query;
    }

    public function missingWeatherQuery(?Carbon $from = null, ?Carbon $to = null): Builder
    {
        $query = Job::query()
            ->whereNotNull('ended_at')
            ->where(function (Builder $q) {
                $q->whereDoesntHave('weatherSnapshots', fn (Builder $ws) => $ws->where('moment', WeatherMoment::Start))
                  ->orWhereDoesntHave('weatherSnapshots', fn (Builder $ws) => $ws->where('moment', WeatherMoment::End));
            })
            ->whereDoesntHave('alertDismissals', fn (Builder $q) => $q->where('alert_type', 'missing_weather'));

        if ($from) {
            $query->where('started_at', '>=', $from);
        }
        if ($to) {
            $query->where('started_at', '<=', $to);
        }

        return $query;
    }

    public function overdueQuery(): Builder
    {
        $hours = Setting::get('alert_overdue_hours', 4);

        return Job::query()
            ->whereNull('ended_at')
            ->where('started_at', '<', Carbon::now()->subHours($hours))
            ->whereDoesntHave('alertDismissals', fn (Builder $q) => $q->where('alert_type', 'overdue'));
    }

    public function counts(): array
    {
        $missingGps = $this->missingGpsQuery()->count();
        $missingWeather = $this->missingWeatherQuery()->count();
        $overdue = $this->overdueQuery()->count();

        return [
            'missing_gps' => $missingGps,
            'missing_weather' => $missingWeather,
            'overdue' => $overdue,
            'total' => $missingGps + $missingWeather + $overdue,
        ];
    }

    public function forType(string $type, array $filters = []): Builder
    {
        if (($filters['status'] ?? null) === 'resolved') {
            return AlertDismissal::query()->where('alert_type', $type);
        }

        $from = isset($filters['date_from']) ? Carbon::parse($filters['date_from']) : null;
        $to = isset($filters['date_to']) ? Carbon::parse($filters['date_to']) : null;

        return match ($type) {
            'missing_gps' => $this->missingGpsQuery($from, $to),
            'missing_weather' => $this->missingWeatherQuery($from, $to),
            'overdue' => $this->overdueQuery(),
            default => Job::query()->whereRaw('1 = 0'),
        };
    }

    public function resolve(int $jobId, string $type, ?string $note, int $userId): AlertDismissal
    {
        return AlertDismissal::updateOrCreate(
            ['job_id' => $jobId, 'alert_type' => $type],
            [
                'note' => $note,
                'resolved_at' => Carbon::now(),
                'resolved_by' => $userId,
            ],
        );
    }

    public function bulkResolve(string $type, int $userId): int
    {
        $query = $this->forType($type);
        $jobIds = $query->pluck('id');

        $now = Carbon::now();
        $count = 0;

        foreach ($jobIds as $jobId) {
            AlertDismissal::updateOrCreate(
                ['job_id' => $jobId, 'alert_type' => $type],
                [
                    'resolved_at' => $now,
                    'resolved_by' => $userId,
                ],
            );
            $count++;
        }

        return $count;
    }

    public function openCount(): int
    {
        return $this->counts()['total'];
    }
}
