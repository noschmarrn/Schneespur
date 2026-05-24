<?php

namespace App\Services\Scheduler\Tasks;

use App\Services\Scheduler\ScheduledTaskInterface;
use Illuminate\Support\Facades\Artisan;

class RetentionDeleteTask implements ScheduledTaskInterface
{
    public function slug(): string
    {
        return 'retention-delete';
    }

    public function label(): string
    {
        return __('scheduler.task_retention_delete');
    }

    public function schedule(): string
    {
        return '0 3 * * *';
    }

    public function handle(): void
    {
        Artisan::call('jobs:retention-delete');
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
