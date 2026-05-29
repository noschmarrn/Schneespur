<?php

namespace App\Jobs;

use App\Mail\CustomerReportMail;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Services\NotificationLogService;
use App\Services\PdfReportService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Diagnostic\DiagnosticManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCustomerReportEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public Carbon $from,
        public Carbon $to,
        public ?CustomerObject $customerObject = null,
    ) {}

    public function handle(PdfReportService $pdfReportService, NotificationLogService $notificationLogService): void
    {
        $type = 'customer_report_email';
        $object = $this->customerObject;
        $recipients = $this->resolveRecipients();

        if (empty($recipients)) {
            Log::warning('SendCustomerReportEmail: no recipient email', [
                'customer_id' => $this->customer->id,
                'customer_object_id' => $object?->id,
            ]);

            return;
        }

        $jobQuery = $object
            ? $object->serviceJobs()
            : $this->customer->serviceJobs();

        $jobCount = $jobQuery
            ->where('started_at', '>=', $this->from)
            ->where('started_at', '<=', $this->to->copy()->endOfDay())
            ->whereNotNull('ended_at')
            ->count();

        $metadata = [
            'period_from' => $this->from->toDateString(),
            'period_to' => $this->to->toDateString(),
            'job_count' => $jobCount,
            'customer_object_id' => $object?->id,
            'customer_object_name' => $object?->name,
        ];

        $pdfContent = null;
        $pdfFilename = '';

        try {
            $pdfContent = $object
                ? $pdfReportService->generateObjectReport($object, $this->from, $this->to)
                : $pdfReportService->generateCustomerReport($this->customer, $this->from, $this->to);
            $pdfFilename = $object
                ? $pdfReportService->objectReportFilename($object, $this->from, $this->to)
                : $pdfReportService->customerReportFilename($this->customer, $this->from, $this->to);
            $metadata['pdf_size_bytes'] = strlen($pdfContent);
        } catch (\Throwable $e) {
            Log::warning('PDF generation failed for customer report email', [
                'customer_id' => $this->customer->id,
                'customer_object_id' => $object?->id,
                'error' => $e->getMessage(),
            ]);

            try {
                app(DiagnosticManager::class)->report('pdf_generation_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'customer_id' => $this->customer->id,
                    'customer_object_id' => $object?->id,
                    'source' => 'SendCustomerReportEmail',
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }

            foreach ($recipients as $recipient) {
                $notificationLogService->logFailedForCustomer(
                    $this->customer,
                    $type,
                    $recipient,
                    'PDF generation failed: ' . $e->getMessage(),
                    $metadata,
                );
            }

            return;
        }

        if (strlen($pdfContent) > 25 * 1024 * 1024) {
            foreach ($recipients as $recipient) {
                $notificationLogService->logFailedForCustomer(
                    $this->customer,
                    $type,
                    $recipient,
                    'PDF exceeds 25 MB size limit',
                    $metadata,
                );
            }

            return;
        }

        foreach (array_unique($recipients) as $recipient) {
            try {
                Mail::to($recipient)->send(new CustomerReportMail(
                    $this->customer,
                    $this->from,
                    $this->to,
                    $pdfContent,
                    $pdfFilename,
                    $this->customerObject,
                ));

                $notificationLogService->logSentForCustomer(
                    $this->customer,
                    $type,
                    $recipient,
                    $metadata,
                );
            } catch (\Throwable $e) {
                $notificationLogService->logFailedForCustomer(
                    $this->customer,
                    $type,
                    $recipient,
                    $e->getMessage(),
                    $metadata,
                );

                try {
                    app(DiagnosticManager::class)->report('mail_send_failed', [
                        'error' => $e->getMessage(),
                        'exception_class' => get_class($e),
                    ], [
                        'customer_id' => $this->customer->id,
                        'customer_object_id' => $object?->id,
                        'recipient' => $recipient,
                        'source' => 'SendCustomerReportEmail',
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
    private function resolveRecipients(): array
    {
        $object = $this->customerObject;
        $customer = $this->customer;

        if (! $object) {
            $email = $customer->notification_email ?? $customer->email;

            return $email ? [$email] : [];
        }

        $mode = $object->notify_recipients ?? 'customer';
        $customerEmail = $customer->notification_email ?? $customer->email;
        $objectEmail = $object->contact_email;

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
