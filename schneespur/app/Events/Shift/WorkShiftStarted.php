<?php

namespace App\Events\Shift;

use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkShiftStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WorkShift $workShift,
        public User $user,
    ) {}
}
