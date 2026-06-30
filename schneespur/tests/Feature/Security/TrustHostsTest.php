<?php

namespace Tests\Feature\Security;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustHosts;
use Tests\TestCase;

class TrustHostsTest extends TestCase
{
    public function test_trust_hosts_middleware_is_registered(): void
    {
        // Laravel only enforces TrustHosts outside local/testing, so behaviour
        // can't be exercised here — assert it is wired into the global stack
        // (it then derives the allowlist from APP_URL in production).
        $kernel = app(Kernel::class);

        $this->assertContains(TrustHosts::class, $kernel->getGlobalMiddleware());
    }
}
