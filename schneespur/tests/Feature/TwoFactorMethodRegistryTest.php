<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\TwoFactorMethodInterface;
use App\Services\Extension\TwoFactorMethodRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Schneespur\Module\Example\Auth\DummyTwoFactorMethod;
use Tests\TestCase;

class TwoFactorMethodRegistryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TwoFactorMethodRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new TwoFactorMethodRegistry();
    }

    public function test_empty_registry_returns_no_methods(): void
    {
        $this->assertSame([], $this->registry->getMethods());
    }

    public function test_register_method_stores_class_string(): void
    {
        $this->registry->registerMethod('totp', DummyTwoFactorMethod::class);

        $methods = $this->registry->getMethods();

        $this->assertArrayHasKey('totp', $methods);
        $this->assertSame(DummyTwoFactorMethod::class, $methods['totp']);
    }

    public function test_get_methods_returns_all_registered(): void
    {
        $this->registry->registerMethod('totp', DummyTwoFactorMethod::class);
        $this->registry->registerMethod('webauthn', DummyTwoFactorMethod::class);

        $methods = $this->registry->getMethods();

        $this->assertCount(2, $methods);
        $this->assertArrayHasKey('totp', $methods);
        $this->assertArrayHasKey('webauthn', $methods);
    }

    public function test_registry_resolves_from_container_as_singleton(): void
    {
        $registry = $this->app->make(TwoFactorMethodRegistry::class);

        $this->assertInstanceOf(TwoFactorMethodRegistry::class, $registry);
        $this->assertSame($registry, $this->app->make(TwoFactorMethodRegistry::class));
    }

    public function test_overwriting_slug_logs_warning(): void
    {
        Log::spy();

        $this->registry->registerMethod('totp', DummyTwoFactorMethod::class);
        $this->registry->registerMethod('totp', DummyTwoFactorMethod::class);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => str_contains($msg, 'overwriting') && str_contains($msg, 'totp'))
            ->once();
    }

    public function test_example_module_registers_dummy_2fa_method(): void
    {
        $this->bootExampleModule();

        $registry = $this->app->make(TwoFactorMethodRegistry::class);

        $this->assertTrue($registry->has('dummy-2fa'));
        $this->assertSame(DummyTwoFactorMethod::class, $registry->resolve('dummy-2fa'));
    }

    public function test_dummy_method_enable_verify_disable_lifecycle(): void
    {
        DummyTwoFactorMethod::resetState();

        $user = User::create([
            'name' => 'Test User',
            'email' => '2fa-test-' . uniqid() . '@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Driver;
        $user->save();

        $method = new DummyTwoFactorMethod();

        $this->assertFalse($method->isEnabled($user));

        $method->enable($user);
        $this->assertTrue($method->isEnabled($user));

        $this->assertTrue($method->verify($user, 'any-code'));

        $method->disable($user);
        $this->assertFalse($method->isEnabled($user));
    }

    public function test_get_available_methods_resolves_from_container(): void
    {
        $this->registry->registerMethod('dummy-2fa', DummyTwoFactorMethod::class);

        $available = $this->registry->getAvailableMethods($this->app);

        $this->assertArrayHasKey('dummy-2fa', $available);
        $this->assertInstanceOf(TwoFactorMethodInterface::class, $available['dummy-2fa']);
    }

    public function test_get_by_module_filters_by_module_namespace(): void
    {
        $this->registry->registerMethod('dummy-2fa', DummyTwoFactorMethod::class);
        $this->registry->registerMethod('other', 'App\\Services\\Auth\\OtherMethod');

        $exampleMethods = $this->registry->getByModule('Example');

        $this->assertArrayHasKey('dummy-2fa', $exampleMethods);
        $this->assertArrayNotHasKey('other', $exampleMethods);
    }

    public function test_remove_by_module_clears_module_methods(): void
    {
        $this->registry->registerMethod('dummy-2fa', DummyTwoFactorMethod::class);
        $this->registry->registerMethod('other', 'App\\Services\\Auth\\OtherMethod');

        $this->registry->removeByModule('Example');

        $this->assertFalse($this->registry->has('dummy-2fa'));
        $this->assertTrue($this->registry->has('other'));
    }

    private function bootExampleModule(): void
    {
        $modulePath = base_path('modules/example/src');
        spl_autoload_register(function (string $class) use ($modulePath) {
            $prefix = 'Schneespur\\Module\\Example\\';
            if (! str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $modulePath . '/' . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });

        putenv('EXAMPLE_MODULE_ENABLED=true');
        $_ENV['EXAMPLE_MODULE_ENABLED'] = true;

        $this->app->register(\Schneespur\Module\Example\ExampleServiceProvider::class);
    }
}
