<?php

namespace App\Events;

use App\Models\WeatherSnapshot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WeatherSnapshotCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WeatherSnapshot $snapshot,
    ) {}
}
