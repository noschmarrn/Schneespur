<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\SetUserLocale;
use App\Models\User;
use App\Services\Extension\LocaleRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetUserLocaleTest extends TestCase
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

    private function user(UserRole $role, ?string $locale): User
    {
        $u = User::create([
            'name' => 'U',
            'email' => 'u@test.local',
            'password' => Hash::make('password'),
            'locale' => $locale,
        ]);
        $u->role = $role;
        $u->save();

        return $u->fresh();
    }

    public function test_registered_user_locale_is_applied(): void
    {
        app(LocaleRegistry::class)->add('cs', 'Čeština');
        $user = $this->user(UserRole::Admin, 'cs');

        $request = Request::create('/admin/users', 'GET');
        $request->setUserResolver(fn () => $user);

        App::setLocale('de');
        (new SetUserLocale)->handle($request, fn () => response('ok'));

        $this->assertSame('cs', App::getLocale());
    }

    public function test_null_locale_leaves_app_locale_unchanged(): void
    {
        $user = $this->user(UserRole::Admin, null);

        $request = Request::create('/admin/users', 'GET');
        $request->setUserResolver(fn () => $user);

        App::setLocale('de');
        (new SetUserLocale)->handle($request, fn () => response('ok'));

        $this->assertSame('de', App::getLocale());
    }

    public function test_unregistered_locale_is_ignored(): void
    {
        $user = $this->user(UserRole::Admin, 'xx');

        $request = Request::create('/admin/users', 'GET');
        $request->setUserResolver(fn () => $user);

        App::setLocale('de');
        (new SetUserLocale)->handle($request, fn () => response('ok'));

        $this->assertSame('de', App::getLocale());
    }

    public function test_guest_request_leaves_app_locale_unchanged(): void
    {
        $request = Request::create('/admin/users', 'GET');

        App::setLocale('de');
        (new SetUserLocale)->handle($request, fn () => response('ok'));

        $this->assertSame('de', App::getLocale());
    }

    public function test_admin_area_applies_per_user_locale_over_default(): void
    {
        app(LocaleRegistry::class)->add('cs', 'Čeština');
        $admin = $this->user(UserRole::Admin, 'cs');

        $this->actingAs($admin)->get(route('admin.users.index'));

        $this->assertSame('cs', App::getLocale());
    }
}
