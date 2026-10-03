<?php

namespace App\Services;

use App\Enums\LifecyclePoint;
use App\Enums\WeatherMoment;
use App\Events\JobCompleted;
use App\Events\JobStarted;
use App\Events\Shift\WorkShiftEnded;
use App\Events\Shift\WorkShiftStarted;
use App\Exceptions\JobLifecycleException;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WeatherSnapshot;
use App\Models\WorkShift;
use App\Services\Extension\LifecycleFieldRegistry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class JobLifecycleService
{
    public function startShift(User $user, array $extra = []): WorkShift
    {
        if ($this->findActiveShift($user)) {
            throw JobLifecycleException::shiftAlreadyActive();
        }

        $shift = DB::transaction(function () use ($user, $extra) {
            $shift = WorkShift::create([
                'user_id' => $user->id,
                'started_at' => now(),
            ]);

            $this->persistLifecycleFields(LifecyclePoint::ShiftStart, $shift, $extra, $user);

            return $shift;
        });

        WorkShiftStarted::dispatch($shift, $user);

        return $shift;
    }

    public function endShift(User $user, array $extra = []): WorkShift
    {
        $shift = $this->findActiveShift($user);

        if (! $shift) {
            throw JobLifecycleException::noActiveShift();
        }

        if ($this->findActiveJob($user)) {
            throw JobLifecycleException::activeJobMustEndFirst();
        }

        DB::transaction(function () use ($shift, $extra, $user) {
            $shift->ended_at = now();
            $shift->save();

            $this->persistLifecycleFields(LifecyclePoint::ShiftEnd, $shift, $extra, $user);
        });

        WorkShiftEnded::dispatch($shift, $user);

        return $shift;
    }

    public function startJob(User $user, CustomerObject $customerObject, string $type, ?Vehicle $vehicle = null, array $extra = []): Job
    {
        $shift = $this->findActiveShift($user);

        if (! $shift) {
            throw JobLifecycleException::noActiveShift();
        }

        if ($this->findActiveJob($user)) {
            throw JobLifecycleException::jobAlreadyActive();
        }

        $job = DB::transaction(function () use ($shift, $customerObject, $user, $vehicle, $type, $extra) {
            $job = Job::create([
                'work_shift_id' => $shift->id,
                'customer_id' => $customerObject->customer_id,
                'customer_object_id' => $customerObject->id,
                'user_id' => $user->id,
                'vehicle_id' => $vehicle?->id,
                'type' => $type,
                'started_at' => now(),
                'is_manual' => false,
            ]);

            $this->persistLifecycleFields(LifecyclePoint::JobStart, $job, $extra, $user);

            return $job;
        });

        JobStarted::dispatch($job);

        if ($customerObject->lat !== null && $customerObject->lon !== null) {
            $this->dispatchWeather($job->id, WeatherMoment::Start, (float) $customerObject->lat, (float) $customerObject->lon, $job->started_at);
        }

        return $job;
    }

    public function endJob(User $user, ?string $notes = null, array $extra = [], ?string $internalNotes = null): Job
    {
        $job = $this->findActiveJob($user);

        if (! $job) {
            throw JobLifecycleException::noActiveJob();
        }

        DB::transaction(function () use ($job, $notes, $internalNotes, $extra, $user) {
            $job->ended_at = now();

            if ($notes !== null) {
                $job->notes = $notes;
            }

            if ($internalNotes !== null) {
                $job->internal_notes = $internalNotes;
            }

            $job->save();

            $this->persistLifecycleFields(LifecyclePoint::JobEnd, $job, $extra, $user);

            $this->freezePrice($job);
        });

        $lat = null;
        $lon = null;

        $latestGps = $job->gpsPoints()->latest('timestamp')->first();
        if ($latestGps !== null && $latestGps->lat !== null && $latestGps->lon !== null) {
            $lat = (float) $latestGps->lat;
            $lon = (float) $latestGps->lon;
        } else {
            $object = $job->customerObject;
            if ($object !== null && $object->lat !== null && $object->lon !== null) {
                $lat = (float) $object->lat;
                $lon = (float) $object->lon;
            }
        }

        if ($lat !== null && $lon !== null) {
            $this->dispatchWeather($job->id, WeatherMoment::End, $lat, $lon, $job->ended_at);
            if (! $this->isCronActive()) {
                JobCompleted::dispatch($job, WeatherSnapshot::where('job_id', $job->id)->where('moment', WeatherMoment::End)->exists());
            }
        } else {
            JobCompleted::dispatch($job, false);
        }

        return $job->loadCount('gpsPoints');
    }

    /**
     * Das Festschreiben des Preises darf einen Einsatzabschluß nie aufhalten:
     * ein Überlauf des Betrags oder eine noch nicht migrierte Preistabelle
     * (Shared-Hosting: Dateien sind schon da, die Migration läuft erst im
     * Web-Request) ließe sonst den Fahrer nicht abschließen. Bei einem Fehler
     * bleiben die vier Preisspalten leer, der Einsatz gilt als "ohne Betrag".
     * Ein fehlgeschlagenes SELECT/UPDATE bricht weder in MySQL noch in SQLite
     * die umgebende Transaktion ab.
     */
    private function freezePrice(Job $job): void
    {
        try {
            app(\App\Services\Pricing\JobPricing::class)->freeze($job);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function createManualJob(
        User $driver,
        CustomerObject $customerObject,
        string $type,
        Carbon $startedAt,
        Carbon $endedAt,
        ?string $notes = null,
        ?Vehicle $vehicle = null,
        ?string $internalNotes = null,
    ): Job {
        $shift = WorkShift::create([
            'user_id' => $driver->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);

        WorkShiftStarted::dispatch($shift, $driver);
        WorkShiftEnded::dispatch($shift, $driver);

        $job = Job::create([
            'work_shift_id' => $shift->id,
            'customer_id' => $customerObject->customer_id,
            'customer_object_id' => $customerObject->id,
            'user_id' => $driver->id,
            'vehicle_id' => $vehicle?->id,
            'type' => $type,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'notes' => $notes,
            'internal_notes' => $internalNotes,
            'is_manual' => true,
        ]);

        $this->freezePrice($job);

        JobStarted::dispatch($job);

        if ($customerObject->lat !== null && $customerObject->lon !== null) {
            $this->dispatchWeather($job->id, WeatherMoment::Start, (float) $customerObject->lat, (float) $customerObject->lon, $job->started_at);
            $this->dispatchWeather($job->id, WeatherMoment::End, (float) $customerObject->lat, (float) $customerObject->lon, $job->ended_at);
            if (! $this->isCronActive()) {
                JobCompleted::dispatch($job, WeatherSnapshot::where('job_id', $job->id)->where('moment', WeatherMoment::End)->exists());
            }
        } else {
            JobCompleted::dispatch($job, false);
        }

        return $job;
    }

    public function findActiveJob(User $user): ?Job
    {
        return Job::where('user_id', $user->id)
            ->whereNull('ended_at')
            ->whereHas('workShift', fn ($q) => $q->whereNull('ended_at'))
            ->first();
    }

    public function findActiveShift(User $user): ?WorkShift
    {
        return WorkShift::where('user_id', $user->id)
            ->whereNull('ended_at')
            ->first();
    }

    /**
     * Runs LifecycleFieldRegistry::persist() and translates a critical
     * handler's failure into the lifecycle's own error vocabulary.
     *
     * The registry rethrows the handler's raw exception unchanged when the
     * contribution is marked `critical` — that is correct at the registry's
     * layer, but a raw \RuntimeException reaching a driver controller falls
     * through its `catch (JobLifecycleException $e)` block straight into
     * Laravel's exception handler (an unhandled 500, no flash, form lost).
     * Wrapping it here, once, gives every lifecycle transition the same
     * flash-message path the other JobLifecycleException cases already use,
     * without LifecycleFieldRegistry having to know about this exception
     * type. A JobLifecycleException raised inside a handler is passed
     * through unchanged so its own message is not replaced by the generic
     * one.
     */
    private function persistLifecycleFields(LifecyclePoint $point, Model $entity, array $extra, User $user): void
    {
        try {
            app(LifecycleFieldRegistry::class)->persist($point, $entity, $extra, $user);
        } catch (JobLifecycleException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw JobLifecycleException::criticalFieldFailed($e);
        }
    }

    private function dispatchWeather(int $jobId, WeatherMoment $moment, float $lat, float $lon, ?Carbon $at = null): void
    {
        // Warteschlange oder sofort — die Entscheidung trifft der
        // WeatherDispatcher, damit der Wiederholen-Knopf im Admin dieselbe
        // trifft. Der Rückgabewert interessiert hier nicht: Wetter blockiert
        // keinen Einsatz.
        app(Weather\WeatherDispatcher::class)->dispatch($jobId, $moment, $lat, $lon, $at);
    }

    private function isCronActive(): bool
    {
        return app(Weather\WeatherDispatcher::class)->isCronActive();
    }
}
