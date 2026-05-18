<?php

namespace App\Listeners;

use App\Events\JobCompleted;
use App\Mail\JobCompletedMail;
use App\Services\NotificationLogService;
use App\Services\PdfReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendJobCompletedNotification implements ShouldQueue
{
    public function __construct(
        private NotificationLogService $notificationLogService,
        private PdfReportService $pdfReportService,
    ) {}

    public function handle(JobCompleted $event): void
    {
        $job = $event->job->loadMissing(['customer', 'customerObject', 'user', 'weatherSnapshots']);

        $object = $job->customerObject;
        $customer = $job->customer;

        if (! ($object->auto_notify_email ?? $customer->auto_notify_email)) {
            return;
        }

        $notificationType = $event->isWeatherUpdate ? 'job_completed_updated' : 'job_completed';

        if (! $event->isWeatherUpdate && $this->notificationLogService->hasBeenNotified($job, 'job_completed')) {
            return;
        }

        $recipients = $this->resolveRecipients($object, $customer);

        if (empty($recipients)) {
            $this->notificationLogService->logSkipped($job, $notificationType, 'skipped_no_email');

            return;
        }

        $pdfContent = null;
        $pdfFilename = '';

        try {
            $pdf = $this->pdfReportService->generateJobReport($job);
            $pdfContent = $pdf->output();
            $pdfFilename = $this->pdfReportService->jobReportFilename($job);
        } catch (\Throwable $e) {
            Log::warning('PDF generation failed for job notification', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($pdfContent !== null && strlen($pdfContent) > 10 * 1024 * 1024) {
            $this->notificationLogService->logSkipped($job, $notificationType, 'skipped_pdf_too_large', [
                'pdf_size' => strlen($pdfContent),
            ]);
            $pdfContent = null;
        }

        foreach (array_unique($recipients) as $recipient) {
            try {
                Mail::to($recipient)->send(new JobCompletedMail(
                    $job,
                    $event->weatherAvailable,
                    $pdfContent,
                    $pdfFilename,
                    $event->isWeatherUpdate,
                ));

                $this->notificationLogService->logSent($job, $notificationType, $recipient, [
                    'weather_available' => $event->weatherAvailable,
                    'pdf_attached' => $pdfContent !== null,
                    'customer_object_id' => $object?->id,
                    'customer_object_name' => $object?->name,
                ]);
            } catch (\Throwable $e) {
                $this->notificationLogService->logFailed($job, $notificationType, $recipient, $e->getMessage());
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
