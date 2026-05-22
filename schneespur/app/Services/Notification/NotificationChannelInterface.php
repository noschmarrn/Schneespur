<?php

namespace App\Services\Notification;

use App\Models\Job;

interface NotificationChannelInterface
{
    public function send(Job $job, string $type, array $context): void;

    public function name(): string;

    public function slug(): string;

    public function isEnabled(): bool;
}
