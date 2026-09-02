<?php

namespace Tests\Feature;

use App\Mail\PortalCredentialsMail;
use App\Models\Customer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerMailLocaleTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Mailable::send() opens the locale context around prepareMailableForDelivery(),
     * so a locale() call inside build() runs after the language is already fixed.
     * The constructor is the only place early enough.
     */
    public function test_the_locale_is_set_before_send_reads_it(): void
    {
        $customer = Customer::create([
            'name' => 'Nordwind Hausverwaltung',
            'email' => 'kunde@test.local',
            'locale' => 'de',
        ]);

        // Signature: __construct(Customer $customer, string $plainPassword, bool $isReset = false)
        $mail = new PortalCredentialsMail($customer, 'geheim123');

        // Readable straight after construction — no build() involved.
        $this->assertSame('de', $mail->locale);
    }

    public function test_a_german_customer_gets_german_body_text_under_english_app_locale(): void
    {
        app()->setLocale('en');

        $customer = Customer::create([
            'name' => 'Nordwind Hausverwaltung',
            'email' => 'kunde@test.local',
            'locale' => 'de',
        ]);

        $rendered = (new PortalCredentialsMail($customer, 'geheim123'))->render();

        $this->assertStringContainsString(
            trans('notification.portal_credentials_password_label', [], 'de'),
            $rendered,
        );
    }
}
