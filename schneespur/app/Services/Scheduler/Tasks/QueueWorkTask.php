<?php

namespace App\Services\Scheduler\Tasks;

use App\Services\Scheduler\ScheduledTaskInterface;
use Illuminate\Support\Facades\Artisan;

class QueueWorkTask implements ScheduledTaskInterface
{
    public function slug(): string
    {
        return 'queue-work';
    }

    public function label(): string
    {
        return __('scheduler.task_queue_work');
    }

    public function schedule(): string
    {
        return '* * * * *';
    }

    public function handle(): void
    {
        Artisan::call('queue:work', ['--stop-when-empty' => true]);
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
