<?php

namespace App\Events;

use App\Models\Job;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Job $job,
    ) {}
}
