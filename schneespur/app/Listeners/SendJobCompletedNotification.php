<?php

namespace App\Listeners;

use App\Events\JobCompleted;
use App\Services\Diagnostic\DiagnosticManager;
use App\Services\Extension\FilterRegistry;
use App\Services\Notification\NotificationChannelRegistry;
use App\Services\NotificationLogService;
use App\Services\PdfReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendJobCompletedNotification implements ShouldQueue
{
    public function __construct(
        private NotificationLogService $notificationLogService,
        private PdfReportService $pdfReportService,
        private FilterRegistry $filterRegistry,
        private NotificationChannelRegistry $channelRegistry,
    ) {}

    public function handle(JobCompleted $event): void
    {
        $job = $event->job->loadMissing(['customer', 'customerObject', 'user', 'weatherSnapshots']);

        $object = $job->customerObject;
        $customer = $job->customer;

        if (! ($object->auto_notify_email || $customer->auto_notify_email)) {
            return;
        }

        $notificationType = $event->isWeatherUpdate ? 'job_completed_updated' : 'job_completed';

        if (! $event->isWeatherUpdate && $this->notificationLogService->hasBeenNotified($job, 'job_completed')) {
            return;
        }

        $recipients = $this->resolveRecipients($object, $customer);
        $recipients = $this->filterRegistry->apply('schneespur.job.notification.recipients', $recipients, $job);

        if (empty($recipients)) {
            $this->notificationLogService->logSkipped($job, $notificationType, 'skipped_no_email');

            return;
        }

        $pdfContent = null;
        $pdfFilename = '';

        try {
            $pdfContent = $this->pdfReportService->generateJobReport($job);
            $pdfFilename = $this->pdfReportService->jobReportFilename($job);
        } catch (\Throwable $e) {
            Log::warning('PDF generation failed for job notification', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);

            try {
                app(DiagnosticManager::class)->report('pdf_generation_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'job_id' => $job->id,
                    'customer_id' => $customer?->id,
                    'customer_object_id' => $object?->id,
                    'source' => 'SendJobCompletedNotification',
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }
        }

        if ($pdfContent !== null && strlen($pdfContent) > 10 * 1024 * 1024) {
            $this->notificationLogService->logSkipped($job, $notificationType, 'skipped_pdf_too_large', [
                'pdf_size' => strlen($pdfContent),
            ]);
            $pdfContent = null;
        }

        $context = [
            'recipients' => array_unique($recipients),
            'pdf_content' => $pdfContent,
            'pdf_filename' => $pdfFilename,
            'weather_available' => $event->weatherAvailable,
            'is_weather_update' => $event->isWeatherUpdate,
            'customer_object_id' => $object?->id,
            'customer_object_name' => $object?->name,
        ];

        $results = $this->channelRegistry->dispatch($job, $notificationType, $context);

        foreach ($results as $result) {
            if ($result['status'] === 'failed') {
                Log::warning('Notification channel dispatch failed', [
                    'job_id' => $job->id,
                    'channel' => $result['slug'],
                    'error' => $result['error'],
                ]);

                try {
                    app(DiagnosticManager::class)->report('notification_dispatch_failed', [
                        'error' => $result['error'],
                    ], [
                        'slug' => $result['slug'],
                        'job_id' => $job->id,
                        'notification_type' => $notificationType,
                        'source' => 'SendJobCompletedNotification',
                    ]);
                } catch (\Throwable) {
                    // Never let diagnostic reporting break the original flow
                }
            }
        }
    }

    /**
     * @return string[]
     */
    private function resolveRecipients(?object $object, object $customer): array
    {
        $mode = $object->notify_recipients ?? 'customer';
        $customerEmail = $customer->notification_email ?? $customer->email;
        $objectEmail = $object->contact_email ?? null;

        $recipients = [];

        if (in_array($mode, ['customer', 'both']) && ! empty($customerEmail)) {
            $recipients[] = $customerEmail;
        }

        if (in_array($mode, ['object', 'both']) && ! empty($objectEmail)) {
            $recipients[] = $objectEmail;
        }

        return $recipients;
    }
}
