<?php

namespace App\Jobs;

use App\Enums\WeatherMoment;
use App\Events\JobCompleted;
use App\Models\Job;
use App\Models\WeatherSnapshot;
use App\Services\NotificationLogService;
use App\Services\Weather\WeatherFetcher;
use Carbon\CarbonInterface;
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
        // Der Zeitpunkt, dessen Wetter gemeint ist. null heißt „jetzt" — so
        // verhalten sich laufende Einsätze wie bisher. Vorgabewert, damit ein
        // beim Update noch in der Warteschlange liegender Auftrag mit vier
        // Argumenten weiterhin deserialisiert.
        public readonly ?CarbonInterface $momentAt = null,
        // Setzt der Wiederholen-Knopf: ein vorhandener Schnappschuß mit
        // falschem Zeitpunkt soll dann ersetzt werden dürfen.
        public readonly bool $force = false,
    ) {}

    public function handle(WeatherFetcher $fetcher): void
    {
        // Reihenfolge wie bisher: liegt schon ein geholter Schnappschuß vor,
        // endet der Auftrag stillschweigend und feuert KEIN JobCompleted.
        // Täte er es doch, bekäme der Kunde bei jedem zweiten Queue-Anlauf
        // eine weitere Nachweis-Mail.
        if (! $this->force && $this->weatherAlreadyOnRecord()) {
            return;
        }

        $snapshot = $fetcher->record(
            $this->jobId,
            $this->moment,
            $this->latitude,
            $this->longitude,
            $this->momentAt,
            $this->force,
        );

        if ($snapshot === null) {
            throw new \RuntimeException(
                "Weather fetch failed for job {$this->jobId} ({$this->moment->value})"
            );
        }

        $this->fireJobCompletedIfEndMoment(weatherAvailable: true);
    }

    private function weatherAlreadyOnRecord(): bool
    {
        return WeatherSnapshot::where('job_id', $this->jobId)
            ->where('moment', $this->moment)
            ->whereNotNull('fetched_at')
            ->exists();
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
