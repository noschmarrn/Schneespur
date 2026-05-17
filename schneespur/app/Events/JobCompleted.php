<?php

namespace App\Events;

use App\Models\Job;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Job $job,
        public bool $weatherAvailable,
        public bool $isWeatherUpdate = false,
    ) {}
}
