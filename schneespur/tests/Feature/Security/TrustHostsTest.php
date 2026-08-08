<?php

namespace Tests\Feature\Security;

use Illuminate\Container\Container;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustHosts;
use Tests\TestCase;

class TrustHostsTest extends TestCase
{
    /**
     * Boot a *fresh* application straight from bootstrap/app.php with APP_URL
     * forced to $appUrl, and report its global middleware stack.
     *
     * Re-running the real bootstrap file is the point: the guard being tested
     * lives there, so asserting against a re-derived policy value would prove
     * nothing about the wiring. The container instance is saved and restored
     * because constructing an Application makes itself the global instance.
     *
     * @return array<int, string>
     */
    private function globalMiddlewareWithAppUrl(?string $appUrl): array
    {
        $previousContainer = Container::getInstance();
        $hadServer = array_key_exists('APP_URL', $_SERVER);
        $hadEnv = array_key_exists('APP_URL', $_ENV);
        $previousServer = $_SERVER['APP_URL'] ?? null;
        $previousEnv = $_ENV['APP_URL'] ?? null;

        try {
            if ($appUrl === null) {
                unset($_SERVER['APP_URL'], $_ENV['APP_URL']);
            } else {
                $_SERVER['APP_URL'] = $appUrl;
                $_ENV['APP_URL'] = $appUrl;
            }

            $app = require base_path('bootstrap/app.php');

            return $app->make(Kernel::class)->getGlobalMiddleware();
        } finally {
            if ($hadServer) {
                $_SERVER['APP_URL'] = $previousServer;
            } else {
                unset($_SERVER['APP_URL']);
            }

            if ($hadEnv) {
                $_ENV['APP_URL'] = $previousEnv;
            } else {
                unset($_ENV['APP_URL']);
            }
            Container::setInstance($previousContainer);
        }
    }

    public function test_trust_hosts_is_registered_once_app_url_names_a_real_host(): void
    {
        $this->assertContains(
            TrustHosts::class,
            $this->globalMiddlewareWithAppUrl('https://schneespur.example.com'),
            'A configured installation must still reject Host-header injection.'
        );
    }

    /**
     * The release blocker, at the wiring level. Shipped 1.2.0 answered EVERY
     * request to a freshly uploaded install with 400 Bad Request — /install
     * included — because .env.example's APP_URL default is http://localhost
     * and, with no .env yet, APP_ENV falls back to "production" so Laravel
     * enforced the allowlist against that placeholder. Reproduced on real
     * shared hosting before this guard existed.
     */
    public function test_trust_hosts_is_not_registered_while_app_url_is_still_the_placeholder(): void
    {
        $this->assertNotContains(
            TrustHosts::class,
            $this->globalMiddlewareWithAppUrl('http://localhost'),
            'Enforcing against the placeholder locks every visitor out of the installer.'
        );
    }

    public function test_trust_hosts_is_not_registered_before_any_env_file_exists(): void
    {
        $this->assertNotContains(
            TrustHosts::class,
            $this->globalMiddlewareWithAppUrl(null)
        );
    }
}
