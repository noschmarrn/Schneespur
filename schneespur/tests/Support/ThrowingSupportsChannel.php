<?php

namespace Tests\Support;

use App\Models\Job;
use App\Services\Notification\NotificationChannelInterface;
use App\Services\Notification\SupportsNotificationTypes;

/**
 * A channel whose supports() throws. Stands in for a misbehaving module
 * channel: dispatch() must isolate this the same way it isolates a throwing
 * send(), so a channel registered after this one is still called.
 */
class ThrowingSupportsChannel implements NotificationChannelInterface, SupportsNotificationTypes
{
    public function supports(string $type): bool
    {
        throw new \RuntimeException('supports() blew up');
    }

    public function send(Job $job, string $type, array $context): void
    {
        // Unreachable if supports() is checked first, as it must be.
    }

    public function name(): string
    {
        return 'Throwing';
    }

    public function slug(): string
    {
        return 'throwing';
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
