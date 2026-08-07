<?php

namespace Tests\Support;

use App\Models\Job;
use App\Services\Notification\NotificationChannelInterface;

/**
 * Stands in for the telegram module: implements the published interface and
 * nothing else. It must keep being called for every type, or that module dies
 * on its next update.
 */
class LegacyTestChannel implements NotificationChannelInterface
{
    public static bool $called = false;

    public function send(Job $job, string $type, array $context): void
    {
        self::$called = true;
    }

    public function name(): string
    {
        return 'Legacy';
    }

    public function slug(): string
    {
        return 'legacy';
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
