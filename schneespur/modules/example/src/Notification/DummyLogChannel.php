<?php

namespace Schneespur\Module\Example\Notification;

use App\Models\Job;
use App\Services\Notification\NotificationChannelInterface;
use Illuminate\Support\Facades\Log;

class DummyLogChannel implements NotificationChannelInterface
{
    public function send(Job $job, string $type, array $context): void
    {
        Log::info('DummyLogChannel: notification dispatched', [
            'job_id' => $job->id,
            'type' => $type,
            'recipient_count' => count($context['recipients'] ?? []),
        ]);
    }

    public function name(): string
    {
        return 'Log (Demo)';
    }

    public function slug(): string
    {
        return 'dummy-log';
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
