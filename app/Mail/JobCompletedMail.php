<?php

namespace App\Mail;

use App\Models\Job;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Job $job,
        public bool $weatherAvailable,
        public ?string $pdfContent = null,
        public string $pdfFilename = '',
        public bool $isWeatherUpdate = false,
    ) {}

    public function envelope(): Envelope
    {
        $subjectKey = $this->isWeatherUpdate
            ? 'notification.job_completed_updated_subject'
            : 'notification.job_completed_subject';

        $objectName = $this->job->customerObject?->name;
        $customerLabel = $objectName
            ? $this->job->customer->name . ' – ' . $objectName
            : $this->job->customer->name;

        return new Envelope(
            subject: __($subjectKey, [
                'customer' => $customerLabel,
                'date' => $this->job->started_at->format(__('notification.date_format')),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.job-completed',
            with: [
                'job' => $this->job,
                'weatherAvailable' => $this->weatherAvailable,
                'isWeatherUpdate' => $this->isWeatherUpdate,
                'pdfAttached' => $this->pdfContent !== null,
                'pdfSkipped' => $this->pdfContent === null && $this->pdfFilename !== '',
            ],
        );
    }

    public function build(): static
    {
        $this->locale($this->job->customer->locale ?? 'de');

        if ($this->pdfContent !== null) {
            $this->attachData($this->pdfContent, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
        }

        return $this;
    }
}
