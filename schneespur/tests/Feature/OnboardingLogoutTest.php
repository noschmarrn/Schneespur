<?php

namespace Tests\Feature;

use Tests\TestCase;

class OnboardingLogoutTest extends TestCase
{
    public function test_onboarding_gate_offers_logout(): void
    {
        $blade = file_get_contents(resource_path('views/onboarding/dsgvo.blade.php'));

        $this->assertStringContainsString("route('logout')", $blade);
        $this->assertStringContainsString("__('driver.nav_logout')", $blade);
    }
}
