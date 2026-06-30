<?php

namespace Tests\Feature\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
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

    public function test_x_forwarded_for_is_not_trusted_by_default(): void
    {
        config(['session.driver' => 'array']);

        // On stock web hosting we don't know what (if anything) sits in front,
        // so a client-supplied X-Forwarded-For must NOT be believed — otherwise
        // an attacker rotates the header to defeat the per-IP login throttle.
        Route::get('/__test_client_ip', fn (Request $r) => $r->ip())->middleware('web');

        $response = $this->get('/__test_client_ip', ['X-Forwarded-For' => '203.0.113.77']);

        $response->assertOk();
        $response->assertDontSee('203.0.113.77');
        $response->assertSee('127.0.0.1');
    }
}
