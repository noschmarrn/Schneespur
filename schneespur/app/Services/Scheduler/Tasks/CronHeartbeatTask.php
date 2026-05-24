<?php

namespace App\Services\Scheduler\Tasks;

use App\Services\Scheduler\ScheduledTaskInterface;

class CronHeartbeatTask implements ScheduledTaskInterface
{
    public function slug(): string
    {
        return 'cron-heartbeat';
    }

    public function label(): string
    {
        return __('scheduler.task_cron_heartbeat');
    }

    public function schedule(): string
    {
        return '* * * * *';
    }

    public function handle(): void
    {
        cache()->put('cron.last_run', now());
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
