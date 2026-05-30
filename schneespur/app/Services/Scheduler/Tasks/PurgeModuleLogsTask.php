<?php

namespace App\Services\Scheduler\Tasks;

use App\Services\Scheduler\ScheduledTaskInterface;
use Illuminate\Support\Facades\Artisan;

class PurgeModuleLogsTask implements ScheduledTaskInterface
{
    public function slug(): string
    {
        return 'purge-module-logs';
    }

    public function label(): string
    {
        return __('scheduler.task_purge_module_logs');
    }

    public function schedule(): string
    {
        return '30 3 * * *';
    }

    public function handle(): void
    {
        Artisan::call('modlogs:purge');
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function source(): string
    {
        return 'core';
    }
}
