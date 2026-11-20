<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\OwntracksCredentialEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Credentials used to be generated only by "Drivers → New driver". An account
 * that got the driver role through user management (or the installer's admin,
 * given the role later) had none, and the driver page offered no way to create
 * them — the button only appeared once a username already existed.
 */
class DriverCredentialGenerationTest extends TestCase
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

    private function adminWhoIsAlsoDriver(): User
    {
        $user = User::create([
            'name' => 'Chefin Fahrerin',
            'email' => 'chefin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Admin;
        $user->save();
        $user->assignRole('admin');
        $user->assignRole('driver');

        return $user->fresh();
    }

    public function test_driver_page_offers_to_generate_missing_credentials(): void
    {
        $user = $this->adminWhoIsAlsoDriver();
        $this->assertNull($user->owntracks_username);

        $this->actingAs($user)
            ->get(route('admin.drivers.edit', $user))
            ->assertOk()
            ->assertSee(route('admin.drivers.rotate-credentials', $user), false)
            ->assertSee(__('driver.btn_generate_credentials'))
            ->assertDontSee(__('driver.btn_rotate_password'));
    }

    public function test_generating_creates_credentials_and_shows_them_once(): void
    {
        $user = $this->adminWhoIsAlsoDriver();

        $this->actingAs($user)
            ->post(route('admin.drivers.rotate-credentials', $user))
            ->assertRedirect(route('admin.drivers.credentials', $user))
            ->assertSessionHas('success', __('driver.flash_credentials_generated'));

        $user->refresh();
        $this->assertNotNull($user->owntracks_username);
        $this->assertNotNull($user->owntracks_password_hash);
        $this->assertSame('created', OwntracksCredentialEvent::where('driver_id', $user->id)->value('event'));
    }

    public function test_driver_with_credentials_still_gets_the_rotate_button(): void
    {
        $user = $this->adminWhoIsAlsoDriver();
        $user->owntracks_username = 'driver-'.$user->id.'-abcd';
        $user->owntracks_password_hash = Hash::make('alt');
        $user->save();

        $this->actingAs($user)
            ->get(route('admin.drivers.edit', $user))
            ->assertOk()
            ->assertSee(__('driver.btn_rotate_password'))
            ->assertDontSee(__('driver.btn_generate_credentials'));

        $this->actingAs($user)
            ->post(route('admin.drivers.rotate-credentials', $user))
            ->assertSessionHas('success', __('driver.flash_rotated'));
    }
}
