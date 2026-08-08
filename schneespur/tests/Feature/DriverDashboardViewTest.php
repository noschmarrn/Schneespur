<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverDashboardViewTest extends TestCase
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

    private function createDriver(): User
    {
        $user = User::create([
            'name' => 'Driver User',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Driver;
        $user->dsgvo_informed_at = now();
        $user->confirmed_version = 99;
        $user->save();

        return $user->fresh();
    }

    /**
     * Alpine invokes an x-data object's own init() method automatically
     * (alpinejs module.esm.js: `reactiveData["init"] && evaluate(el, ...)`, with
     * directiveOrder putting `data` before `init`). Writing x-init="init()" on the
     * same element therefore runs it TWICE: two fetchState() requests per load,
     * doubled online/offline listeners, and — unlike startPolling(), which calls
     * stopPolling() first — startTimer() has no guard, so the first
     * setInterval(..., 1000) handle is overwritten and leaks for the page's life.
     *
     * This is a markup assertion because the duplication is not observable from
     * the server and this repo has no DOM/Alpine harness. The positive x-data
     * assertion is what keeps it honest: without it, a typo in the x-init string
     * would make the absence check pass over any page at all.
     */
    public function test_the_dashboard_does_not_invoke_its_alpine_init_a_second_time(): void
    {
        $response = $this->actingAs($this->createDriver())->get(route('driver.home'));

        $response->assertOk();
        $response->assertSee('x-data="driverDashboard()"', false);
        $response->assertDontSee('x-init="init()"', false);
    }
}
