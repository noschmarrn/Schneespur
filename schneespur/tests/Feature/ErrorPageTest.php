<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    public function test_an_unknown_url_returns_the_own_error_page(): void
    {
        app()->setLocale('de');

        $response = $this->get('/portal/quatsch-tippfehler');

        $response->assertNotFound();
        $response->assertSee(__('errors.404_headline'));
        $response->assertDontSee('NOT FOUND', false);
    }

    public function test_the_error_page_offers_a_way_back(): void
    {
        $response = $this->get('/quatsch-tippfehler');

        $response->assertNotFound();
        $response->assertSee(__('errors.404_back'));
    }
}
