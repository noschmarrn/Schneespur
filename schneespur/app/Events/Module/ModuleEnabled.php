<?php

namespace App\Events\Module;

use App\Models\Module;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModuleEnabled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Module $module,
    ) {}
}
