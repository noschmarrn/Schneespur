<?php

namespace App\Events\Vehicle;

use App\Models\Vehicle;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VehicleDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Vehicle $vehicle,
    ) {}
}
