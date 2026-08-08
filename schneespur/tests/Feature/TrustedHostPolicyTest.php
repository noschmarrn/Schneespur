<?php

namespace Tests\Feature;

use App\Support\TrustedHostPolicy;
use Tests\TestCase;

/**
 * Regression guard for a release blocker found by deploying 1.2.0 to real
 * shared hosting: with no .env yet, APP_ENV falls back to "production" and
 * APP_URL to "http://localhost", so Laravel's TrustHosts trusted only
 * ^(.+\.)?localhost$ and answered EVERY request with 400 Bad Request —
 * including /install. The product could not be installed at all.
 *
 * InstallerBootstrap::ensureAppUrl() is written to detect and store the real
 * host, but it never runs: TrustHosts is global middleware and fires first,
 * and ensureAppUrl() itself calls $request->getHost(), which is the very call
 * that throws.
 *
 * So the host allow-list may only be enforced once there is a real APP_URL to
 * enforce it against.
 */
class TrustedHostPolicyTest extends TestCase
{
    public function test_it_enforces_for_a_configured_host(): void
    {
        $this->assertTrue(TrustedHostPolicy::shouldEnforce('https://claude.destubn.com'));
    }

    public function test_it_does_not_enforce_for_the_stock_default_url(): void
    {
        $this->assertFalse(
            TrustedHostPolicy::shouldEnforce('http://localhost'),
            'The .env.example default must not lock out a fresh install before the installer has run.'
        );
    }

    public function test_it_does_not_enforce_when_app_url_is_absent(): void
    {
        $this->assertFalse(TrustedHostPolicy::shouldEnforce(null));
        $this->assertFalse(TrustedHostPolicy::shouldEnforce(''));
    }

    public function test_it_does_not_enforce_when_app_url_has_no_parseable_host(): void
    {
        $this->assertFalse(TrustedHostPolicy::shouldEnforce('not-a-url'));
    }

    public function test_it_still_enforces_for_a_localhost_subdomain_style_real_host(): void
    {
        // Guards against a lazy `str_contains($url, 'localhost')` implementation.
        $this->assertTrue(TrustedHostPolicy::shouldEnforce('https://localhost.example.com'));
    }
}
