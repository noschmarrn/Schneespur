<?php

namespace Tests\Feature;

use App\Mail\PortalCredentialsMail;
use App\Models\Customer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MailPlainTextTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_plain_text_body_carries_no_markdown_markup(): void
    {
        $customer = Customer::create([
            'name' => 'Nordwind Hausverwaltung',
            'email' => 'kunde@test.local',
            'locale' => 'de',
        ]);

        // The text template is rendered directly: the HTML part keeps its
        // markup on purpose, only the plain-text part must be free of it.
        $rendered = view('mail.portal-credentials-text', [
            'customer' => $customer,
            'password' => 'geheim123',
            'isReset' => false,
        ])->render();

        $this->assertStringNotContainsString('**', $rendered);
        $this->assertStringContainsString('geheim123', $rendered);
        $this->assertStringContainsString('kunde@test.local', $rendered);
    }

    public function test_the_mailable_declares_the_text_template(): void
    {
        $customer = Customer::create([
            'name' => 'Nordwind Hausverwaltung',
            'email' => 'kunde@test.local',
            'locale' => 'de',
        ]);

        $this->assertSame(
            'mail.portal-credentials-text',
            (new PortalCredentialsMail($customer, 'geheim123'))->content()->text,
        );
    }
}
