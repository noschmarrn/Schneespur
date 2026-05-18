<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CustomerEmailChangedMail extends Mailable
{
    public function __construct(
        public Customer $customer,
        public string $oldEmail,
        public string $newEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('portal.email_changed_admin_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.customer-email-changed',
            with: [
                'customer' => $this->customer,
                'oldEmail' => $this->oldEmail,
                'newEmail' => $this->newEmail,
            ],
        );
    }
}
