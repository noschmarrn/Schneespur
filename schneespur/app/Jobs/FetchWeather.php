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
        // verhalten sich laufende Einsätze wie bisher. Der Vorgabewert ist für
        // Aufrufer da, die weiterhin nur vier Argumente reichen; für einen
        // beim Update schon in der Warteschlange liegenden Auftrag tut er
        // nichts — siehe handle().
        public readonly ?CarbonInterface $momentAt = null,
        // Setzt der Wiederholen-Knopf: ein vorhandener Schnappschuß mit
        // falschem Zeitpunkt soll dann ersetzt werden dürfen.
        public readonly bool $force = false,
    ) {}

    public function handle(WeatherFetcher $fetcher): void
    {
        // ?? statt eines direkten Zugriffs: Die Warteschlange serialisiert das
        // Auftrags-*Objekt*, und unserialize() ruft keinen Konstruktor auf —
        // ein Vorgabewert eines Parameters greift dort also nie. Eine
        // Nutzlast, die beim Update schon in der Warteschlange lag, kennt
        // diese beiden Eigenschaften nicht und stellt sie uninitialisiert
        // wieder her; ein direktes $this->force wäre dann ein Error auf einer
        // typisierten Eigenschaft. ?? darf uninitialisiert lesen.
        $force = $this->force ?? false;
        $momentAt = $this->momentAt ?? null;

        // Reihenfolge wie bisher: liegt schon ein geholter Schnappschuß vor,
        // endet der Auftrag stillschweigend und feuert KEIN JobCompleted.
        // Täte er es doch, bekäme der Kunde bei jedem zweiten Queue-Anlauf
        // eine weitere Nachweis-Mail.
        if (! $force && $this->weatherAlreadyOnRecord()) {
            return;
        }

        $snapshot = $fetcher->record(
            $this->jobId,
            $this->moment,
            $this->latitude,
            $this->longitude,
            $momentAt,
            $force,
        );

        if ($snapshot === null) {
            // Ein Anbieter ohne Archiv ist kein Fehlschlag, sondern ein
            // erwartetes Ergebnis: die Ausnahme kostete auf einer
            // MET-Norway-Installation bei JEDEM nacherfaßten Einsatz zwei
            // Versuche, 30 Sekunden Rückfallzeit, eine „permanently failed"-
            // Warnung, die wie ein Vorfall aussieht, und ebenso lange Verzug,
            // bis der Kunde seinen Nachweis bekommt. Der Nachweis geht darum
            // gleich hier raus — ohne Wetter, was die vorhandene
            // Datenqualitätswarnung ohnehin sichtbar macht.
            if (! $fetcher->canFetch($momentAt)) {
                if (! $force) {
                    $this->fireJobCompletedIfEndMoment(weatherAvailable: false);
                }

                return;
            }

            throw new \RuntimeException(
                "Weather fetch failed for job {$this->jobId} ({$this->moment->value})"
            );
        }

        // Ein erzwungener Lauf schweigt. $force setzt einzig der Wiederholen-
        // Knopf im Admin, das heißt: hier korrigiert jemand gespeicherte
        // Daten. Feuerte das JobCompleted, ginge pro Klick eine weitere
        // Nachweis-Mail samt neu erzeugtem PDF an den Kunden —
        // SendJobCompletedNotification entdoppelt nur den Zweig
        // ! isWeatherUpdate, die Variante „aktualisiert" also gar nicht. Daß
        // ein Kunde von einer Korrektur erfährt, ist ein echter Bedarf, aber
        // der gehört an einen eigenen, bewußten Knopf, nicht an einen
        // Diagnoseweg.
        if ($force) {
            return;
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

        // Derselbe Wächter wie in handle(), und aus demselben Grund: $force
        // setzt einzig der Wiederholen-Knopf im Admin. Ohne ihn schickte
        // ausgerechnet der Klick, der nichts Brauchbares gefunden hat, dem
        // Kunden einen frisch gerenderten „Aktualisierter Einsatznachweis"
        // mit dem Vermerk, es lägen keine Wetterdaten vor — und zwar je
        // Klick einmal, weil SendJobCompletedNotification den Zweig
        // isWeatherUpdate nicht entdoppelt. Das ?? liest wie in handle() eine
        // beim Update schon in der Warteschlange liegende Nutzlast mit.
        if ($this->force ?? false) {
            return;
        }

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
