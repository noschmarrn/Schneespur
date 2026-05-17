<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\CustomerObject;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public Carbon $periodFrom,
        public Carbon $periodTo,
        public ?string $pdfContent = null,
        public string $pdfFilename = '',
        public ?CustomerObject $customerObject = null,
    ) {}

    public function envelope(): Envelope
    {
        $objectName = $this->customerObject?->name;
        $customerLabel = $objectName
            ? $this->customer->name . ' – ' . $objectName
            : $this->customer->name;

        return new Envelope(
            subject: __('notification.customer_report_subject', [
                'customer' => $customerLabel,
                'from' => $this->periodFrom->format(__('notification.date_format')),
                'to' => $this->periodTo->format(__('notification.date_format')),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.customer-report',
            with: [
                'customer' => $this->customer,
                'customerObject' => $this->customerObject,
                'from' => $this->periodFrom,
                'to' => $this->periodTo,
                'pdfAttached' => $this->pdfContent !== null,
            ],
        );
    }

    public function build(): static
    {
        $this->locale($this->customer->locale ?? 'de');

        if ($this->pdfContent !== null) {
            $this->attachData($this->pdfContent, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
        }

        $this->replyTo(config('mail.from.address'));

        return $this;
    }
}
