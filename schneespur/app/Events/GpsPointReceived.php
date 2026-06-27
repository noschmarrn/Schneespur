<?php

namespace App\Events;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired for every valid OwnTracks location ping, whether or not the driver
 * currently has an active job. Lets modules (e.g. geofencing) react to live
 * driver position before a job exists and auto-start one.
 */
class GpsPointReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public float $lat,
        public float $lon,
        public int $timestamp,
        public ?int $accuracy = null,
        public ?Job $activeJob = null,
    ) {}
}
