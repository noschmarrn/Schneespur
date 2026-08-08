<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The driver dashboard view used to be served under TWO urls: /driver (the PWA
 * start_url) and /dashboard (where login lands and where the driver layout's own
 * brand link points). Only /driver bootstraps the offline sync queue —
 * resources/js/app.js gates it on pathname.startsWith('/driver') — so on
 * /dashboard an offline job completion was cancelled by preventDefault() and then
 * silently dropped, with no queue to put it in and no flash to say so.
 *
 * /dashboard is now a router, not a second renderer.
 */
class DashboardRoutingTest extends TestCase
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

    private function createUser(?string $roleSlug, string $email): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        if ($roleSlug !== null) {
            $user->role = UserRole::from($roleSlug);
        }

        // Otherwise EnsureDsgvoInformed bounces every non-admin to /onboarding/dsgvo
        // before the dashboard route is ever reached.
        $user->dsgvo_informed_at = now();
        $user->confirmed_version = 99;
        $user->save();

        return $user->fresh();
    }

    public function test_a_driver_visiting_dashboard_is_redirected_to_the_driver_route(): void
    {
        $driver = $this->createUser('driver', 'driver@test.local');

        $response = $this->actingAs($driver)->get(route('dashboard'));

        $response->assertRedirect(route('driver.home'));
    }

    public function test_an_admin_visiting_dashboard_still_reaches_the_admin_dashboard(): void
    {
        $admin = $this->createUser('admin', 'admin@test.local');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertRedirect(route('admin.dashboard'));
    }

    /**
     * The loop guard. EnsureDriver sends every non-driver back to route('dashboard'),
     * so redirecting on `! isAdmin()` instead of on `isDriver()` would bounce a
     * role-less user between /dashboard and /driver until the browser gives up.
     */
    public function test_a_user_who_is_neither_admin_nor_driver_is_not_bounced_between_the_two_routes(): void
    {
        $nobody = $this->createUser(null, 'nobody@test.local');

        $response = $this->actingAs($nobody)->get(route('dashboard'));

        $response->assertOk();
        $this->assertFalse(
            $response->isRedirect(route('driver.home')),
            'A role-less user was redirected to /driver, which EnsureDriver bounces straight back to /dashboard.'
        );
    }
}
