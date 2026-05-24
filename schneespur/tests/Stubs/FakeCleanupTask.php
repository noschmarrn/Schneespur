<?php

namespace Tests\Stubs;

use App\Models\Setting;
use App\Services\Scheduler\ScheduledTaskInterface;

class FakeCleanupTask implements ScheduledTaskInterface
{
    public bool $shouldThrow = false;

    public string $throwMessage = 'Cleanup failed';

    public function slug(): string
    {
        return 'fake-cleanup';
    }

    public function label(): string
    {
        return 'Test Module Cleanup';
    }

    public function schedule(): string
    {
        return '0 */6 * * *';
    }

    public function handle(): void
    {
        if ($this->shouldThrow) {
            throw new \RuntimeException($this->throwMessage);
        }
    }

    public function isEnabled(): bool
    {
        return Setting::get('scheduled_task.fake-cleanup.enabled', '1') === '1';
    }

    public function source(): string
    {
        return 'module';
    }
}
