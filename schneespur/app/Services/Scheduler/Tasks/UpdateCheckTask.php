<?php

namespace App\Services\Scheduler\Tasks;

use App\Services\Scheduler\ScheduledTaskInterface;
use Illuminate\Support\Facades\Artisan;

class UpdateCheckTask implements ScheduledTaskInterface
{
    public function slug(): string
    {
        return 'update-check';
    }

    public function label(): string
    {
        return __('scheduler.task_update_check');
    }

    public function schedule(): string
    {
        return '17 4 * * *';
    }

    public function handle(): void
    {
        Artisan::call('schneespur:update-check');
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
