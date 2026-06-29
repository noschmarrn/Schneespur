<?php

namespace App\Services;

use App\Enums\WeatherMoment;
use App\Events\JobCompleted;
use App\Events\JobStarted;
use App\Events\Shift\WorkShiftEnded;
use App\Events\Shift\WorkShiftStarted;
use App\Events\WeatherSnapshotCreated;
use App\Exceptions\JobLifecycleException;
use App\Jobs\FetchWeather;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WeatherSnapshot;
use App\Models\WorkShift;
use Carbon\Carbon;

class JobLifecycleService
{
    public function startShift(User $user): WorkShift
    {
        if ($this->findActiveShift($user)) {
            throw JobLifecycleException::shiftAlreadyActive();
        }

        $shift = WorkShift::create([
            'user_id' => $user->id,
            'started_at' => now(),
        ]);

        WorkShiftStarted::dispatch($shift, $user);

        return $shift;
    }

    public function endShift(User $user): WorkShift
    {
        $shift = $this->findActiveShift($user);

        if (! $shift) {
            throw JobLifecycleException::noActiveShift();
        }

        if ($this->findActiveJob($user)) {
            throw JobLifecycleException::activeJobMustEndFirst();
        }

        $shift->ended_at = now();
        $shift->save();

        WorkShiftEnded::dispatch($shift, $user);

        return $shift;
    }

    public function startJob(User $user, CustomerObject $customerObject, string $type, ?Vehicle $vehicle = null): Job
    {
        $shift = $this->findActiveShift($user);

        if (! $shift) {
            throw JobLifecycleException::noActiveShift();
        }

        if ($this->findActiveJob($user)) {
            throw JobLifecycleException::jobAlreadyActive();
        }

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

        JobStarted::dispatch($job);

        if ($customerObject->lat !== null && $customerObject->lon !== null) {
            $this->dispatchWeather($job->id, WeatherMoment::Start, (float) $customerObject->lat, (float) $customerObject->lon);
        }

        return $job;
    }

    public function endJob(User $user, ?string $notes = null): Job
    {
        $job = $this->findActiveJob($user);

        if (! $job) {
            throw JobLifecycleException::noActiveJob();
        }

        $job->ended_at = now();

        if ($notes !== null) {
            $job->notes = $notes;
        }

        $job->save();

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
            $this->dispatchWeather($job->id, WeatherMoment::End, $lat, $lon);
            if (! $this->isCronActive()) {
                JobCompleted::dispatch($job, WeatherSnapshot::where('job_id', $job->id)->where('moment', WeatherMoment::End)->exists());
            }
        } else {
            JobCompleted::dispatch($job, false);
        }

        return $job->loadCount('gpsPoints');
    }

    public function createManualJob(
        User $driver,
        CustomerObject $customerObject,
        string $type,
        Carbon $startedAt,
        Carbon $endedAt,
        ?string $notes = null,
        ?Vehicle $vehicle = null,
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
            'is_manual' => true,
        ]);

        JobStarted::dispatch($job);

        if ($customerObject->lat !== null && $customerObject->lon !== null) {
            $this->dispatchWeather($job->id, WeatherMoment::Start, (float) $customerObject->lat, (float) $customerObject->lon);
            $this->dispatchWeather($job->id, WeatherMoment::End, (float) $customerObject->lat, (float) $customerObject->lon);
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

    private function dispatchWeather(int $jobId, WeatherMoment $moment, float $lat, float $lon): void
    {
        if ($this->isCronActive()) {
            FetchWeather::dispatch($jobId, $moment, $lat, $lon);

            return;
        }

        $this->fetchWeatherSync($jobId, $moment, $lat, $lon);
    }

    private function isCronActive(): bool
    {
        $lastRun = cache()->get('cron.last_run');

        return $lastRun && $lastRun->diffInMinutes(now()) < 5;
    }

    private function fetchWeatherSync(int $jobId, WeatherMoment $moment, float $lat, float $lon): void
    {
        try {
            $registry = app(Weather\WeatherProviderRegistry::class);
            $provider = $registry->resolve();
            $result = $provider->fetchCurrent($lat, $lon);

            if ($result === null) {
                return;
            }

            $snapshot = WeatherSnapshot::updateOrCreate(
                ['job_id' => $jobId, 'moment' => $moment],
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
        } catch (\Throwable) {
            // Weather is never blocking — silently fail
        }
    }
}
