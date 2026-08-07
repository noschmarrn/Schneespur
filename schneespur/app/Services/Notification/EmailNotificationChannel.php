<?php

namespace App\Services\Notification;

use App\Models\Job;
use App\Services\NotificationLogService;
use Illuminate\Support\Facades\Mail;

class EmailNotificationChannel implements NotificationChannelInterface, SupportsNotificationTypes
{
    public function __construct(
        private readonly NotificationLogService $notificationLogService,
        private readonly MailableRegistry $mailables,
    ) {}

    public function supports(string $type): bool
    {
        return $this->mailables->has($type);
    }

    public function send(Job $job, string $type, array $context): void
    {
        if (! $this->mailables->has($type)) {
            // Not this channel's business. Before, every foreign type was
            // sent as "Auftrag abgeschlossen" — a mail to the customer for a
            // job that had not started.
            return;
        }

        $recipients = array_unique($context['recipients'] ?? []);

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)->send($this->mailables->make($type, $job, $context));

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
