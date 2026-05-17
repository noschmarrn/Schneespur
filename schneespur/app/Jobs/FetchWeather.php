<?php

namespace App\Jobs;

use App\Enums\WeatherMoment;
use App\Events\JobCompleted;
use App\Events\WeatherSnapshotCreated;
use App\Models\Job;
use App\Models\WeatherSnapshot;
use App\Services\NotificationLogService;
use App\Services\Weather\WeatherProviderRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchWeather implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public array $backoff = [30];

    public function __construct(
        public readonly int $jobId,
        public readonly WeatherMoment $moment,
        public readonly float $latitude,
        public readonly float $longitude,
    ) {}

    public function handle(WeatherProviderRegistry $registry): void
    {
        $provider = $registry->resolve();
        $result = $provider->fetchCurrent($this->latitude, $this->longitude);

        if ($result === null) {
            throw new \RuntimeException(
                "Weather fetch failed for job {$this->jobId} ({$this->moment->value}) via {$provider->name()}"
            );
        }

        $existing = WeatherSnapshot::where('job_id', $this->jobId)
            ->where('moment', $this->moment)
            ->first();

        if ($existing && $existing->fetched_at !== null) {
            return;
        }

        $snapshot = WeatherSnapshot::updateOrCreate(
            [
                'job_id' => $this->jobId,
                'moment' => $this->moment,
            ],
            [
                'provider' => $result->provider,
                'temperature' => $result->temperature_c,
                'precipitation' => $result->precipitation_mm,
                'snow_depth' => $result->snowfall_mm,
                'wind_speed' => $result->wind_kmh,
                'humidity' => $result->humidity_percent,
                'weather_code' => $result->weather_code,
                'raw_response' => $result->toArray(),
                'fetched_at' => now(),
            ]
        );

        WeatherSnapshotCreated::dispatch($snapshot);

        $this->fireJobCompletedIfEndMoment(weatherAvailable: true);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::warning('FetchWeather permanently failed', [
            'job_id' => $this->jobId,
            'moment' => $this->moment->value,
            'error' => $exception?->getMessage(),
        ]);

        $this->fireJobCompletedIfEndMoment(weatherAvailable: false);
    }

    private function fireJobCompletedIfEndMoment(bool $weatherAvailable): void
    {
        if ($this->moment !== WeatherMoment::End) {
            return;
        }

        $job = Job::find($this->jobId);

        if ($job === null || $job->ended_at === null) {
            return;
        }

        $isWeatherUpdate = app(NotificationLogService::class)->hasBeenNotified($job, 'job_completed');

        JobCompleted::dispatch($job, $weatherAvailable, $isWeatherUpdate);
    }
}
