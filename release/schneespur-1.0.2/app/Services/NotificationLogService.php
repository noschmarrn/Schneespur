<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Job;
use App\Models\NotificationLog;
use Carbon\Carbon;

class NotificationLogService
{
    public function logSent(Job $job, string $type, string $recipient, array $metadata = []): NotificationLog
    {
        return $job->notificationLogs()->create([
            'channel' => 'email',
            'type' => $type,
            'recipient' => $recipient,
            'status' => 'sent',
            'metadata' => $metadata ?: null,
        ]);
    }

    public function logFailed(Job $job, string $type, string $recipient, string $error, array $metadata = []): NotificationLog
    {
        return $job->notificationLogs()->create([
            'channel' => 'email',
            'type' => $type,
            'recipient' => $recipient,
            'status' => 'failed',
            'error_message' => $error,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function logSkipped(Job $job, string $type, string $status, array $metadata = []): NotificationLog
    {
        return $job->notificationLogs()->create([
            'channel' => 'email',
            'type' => $type,
            'status' => $status,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function anonymizeForCustomer(Customer $customer): int
    {
        $jobCount = NotificationLog::query()
            ->where('notifiable_type', Job::class)
            ->whereIn('notifiable_id', function ($query) use ($customer) {
                $query->select('id')
                    ->from('service_jobs')
                    ->where('customer_id', $customer->id);
            })
            ->update(['recipient' => null]);

        $customerCount = NotificationLog::query()
            ->where('notifiable_type', Customer::class)
            ->where('notifiable_id', $customer->id)
            ->update(['recipient' => null]);

        return $jobCount + $customerCount;
    }

    public function hasBeenNotified(Job $job, string $type): bool
    {
        return $job->notificationLogs()
            ->where('type', $type)
            ->where('status', 'sent')
            ->exists();
    }

    public function logSentForCustomer(Customer $customer, string $type, string $recipient, array $metadata = []): NotificationLog
    {
        return $customer->notificationLogs()->create([
            'channel' => 'email',
            'type' => $type,
            'recipient' => $recipient,
            'status' => 'sent',
            'metadata' => $metadata ?: null,
        ]);
    }

    public function logFailedForCustomer(Customer $customer, string $type, string $recipient, string $error, array $metadata = []): NotificationLog
    {
        return $customer->notificationLogs()->create([
            'channel' => 'email',
            'type' => $type,
            'recipient' => $recipient,
            'status' => 'failed',
            'error_message' => $error,
            'metadata' => $metadata ?: null,
        ]);
    }

    public function wasRecentlySentToCustomer(Customer $customer, string $type, Carbon $from, Carbon $to, int $minutes = 5): bool
    {
        return $customer->notificationLogs()
            ->where('type', $type)
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->whereJsonContains('metadata->period_from', $from->toDateString())
            ->whereJsonContains('metadata->period_to', $to->toDateString())
            ->exists();
    }
}
