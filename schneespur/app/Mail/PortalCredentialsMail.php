<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PortalCredentialsMail extends Mailable
{
    public function __construct(
        public Customer $customer,
        public string $plainPassword,
        public bool $isReset = false,
    ) {}

    public function envelope(): Envelope
    {
        $key = $this->isReset
            ? 'notification.portal_credentials_reset_subject'
            : 'notification.portal_credentials_subject';

        return new Envelope(
            subject: __($key),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.portal-credentials',
            with: [
                'customer' => $this->customer,
                'password' => $this->plainPassword,
                'isReset' => $this->isReset,
            ],
        );
    }

    public function build(): static
    {
        $this->locale($this->customer->locale ?? 'de');
        $this->replyTo(config('mail.from.address'));

        return $this;
    }
}
