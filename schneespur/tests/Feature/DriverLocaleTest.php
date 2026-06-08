<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureDriver;
use App\Models\User;
use App\Services\Extension\LocaleRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DriverLocaleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function driver(?string $locale): User
    {
        $u = User::create([
            'name' => 'Pavel',
            'email' => 'pavel@test.local',
            'password' => Hash::make('password'),
            'locale' => $locale,
        ]);
        $u->role = UserRole::Driver;
        $u->save();

        return $u->fresh();
    }

    public function test_registered_driver_locale_is_applied(): void
    {
        app(LocaleRegistry::class)->add('cs', 'Čeština');
        $driver = $this->driver('cs');

        $request = Request::create('/driver', 'GET');
        $request->setUserResolver(fn () => $driver);

        App::setLocale('de');
        (new EnsureDriver)->handle($request, fn () => response('ok'));

        $this->assertSame('cs', App::getLocale());
    }

    public function test_null_locale_leaves_app_locale_unchanged(): void
    {
        $driver = $this->driver(null);

        $request = Request::create('/driver', 'GET');
        $request->setUserResolver(fn () => $driver);

        App::setLocale('de');
        (new EnsureDriver)->handle($request, fn () => response('ok'));

        $this->assertSame('de', App::getLocale());
    }
}
