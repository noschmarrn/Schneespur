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
        ?string $pdfContent = null,
        public string $pdfFilename = '',
        public bool $isWeatherUpdate = false,
    ) {
        // Base64-encode binary PDF so the database queue can JSON-serialize it
        $this->pdfContentBase64 = $pdfContent !== null ? base64_encode($pdfContent) : null;
    }

    public ?string $pdfContentBase64 = null;

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
                'pdfAttached' => $this->pdfContentBase64 !== null,
                'pdfSkipped' => $this->pdfContentBase64 === null && $this->pdfFilename !== '',
            ],
        );
    }

    public function build(): static
    {
        $this->locale($this->job->customer->locale ?? 'de');

        if ($this->pdfContentBase64 !== null) {
            $this->attachData(base64_decode($this->pdfContentBase64), $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
        }

        return $this;
    }
}
