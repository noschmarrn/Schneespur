<?php

namespace Tests\Feature;

use App\Services\Extension\PublicHomepageRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicHomepageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function markInstalled(): void
    {
        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    public function test_root_redirects_to_login_when_no_homepage_registered(): void
    {
        $this->markInstalled();

        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_root_serves_registered_homepage(): void
    {
        $this->markInstalled();

        app(PublicHomepageRegistry::class)->register(
            fn () => response('<h1>Winterdienst Mustermann</h1>', 200)
        );

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Winterdienst Mustermann', false);
    }

    public function test_installer_takes_precedence_over_registered_homepage(): void
    {
        // No installed.lock on purpose.
        @unlink(storage_path('app/installed.lock'));

        app(PublicHomepageRegistry::class)->register(
            fn () => response('homepage', 200)
        );

        $this->get('/')->assertRedirect(route('install.welcome'));
    }
}
