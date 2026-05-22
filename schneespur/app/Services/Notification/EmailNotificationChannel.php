<?php

namespace App\Services\Notification;

use App\Mail\JobCompletedMail;
use App\Models\Job;
use App\Services\NotificationLogService;
use Illuminate\Support\Facades\Mail;

class EmailNotificationChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly NotificationLogService $notificationLogService,
    ) {}

    public function send(Job $job, string $type, array $context): void
    {
        $recipients = array_unique($context['recipients'] ?? []);

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)->send(new JobCompletedMail(
                    $job,
                    $context['weather_available'] ?? false,
                    $context['pdf_content'] ?? null,
                    $context['pdf_filename'] ?? '',
                    $context['is_weather_update'] ?? false,
                ));

                $this->notificationLogService->logSent($job, $type, $recipient, [
                    'weather_available' => $context['weather_available'] ?? false,
                    'pdf_attached' => ($context['pdf_content'] ?? null) !== null,
                    'customer_object_id' => $context['customer_object_id'] ?? null,
                    'customer_object_name' => $context['customer_object_name'] ?? null,
                ], 'email');
            } catch (\Throwable $e) {
                $this->notificationLogService->logFailed($job, $type, $recipient, $e->getMessage(), [], 'email');
                throw $e;
            }
        }
    }

    public function name(): string
    {
        return 'E-Mail';
    }

    public function slug(): string
    {
        return 'email';
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
