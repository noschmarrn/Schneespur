<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Job;
use App\Models\Setting;
use App\Models\User;
use App\Services\Dispatch\DispatchStrategyInterface;
use App\Services\Dispatch\ManualDispatchStrategy;
use App\Services\Extension\DispatchStrategyRegistry;
use App\Services\Extension\FilterRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DispatchStrategyRegistryTest extends TestCase
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

    private function createAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'dispatch-admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $admin->role = UserRole::Admin;
        $admin->save();

        return $admin->fresh();
    }

    public function test_manual_strategy_is_registered_by_default(): void
    {
        $registry = $this->app->make(DispatchStrategyRegistry::class);

        $this->assertTrue($registry->has('manual'));
        $this->assertSame('manual', $registry->activeSlug());
    }

    public function test_resolve_returns_manual_strategy_instance(): void
    {
        $registry = $this->app->make(DispatchStrategyRegistry::class);

        $strategy = $registry->resolve('manual');

        $this->assertInstanceOf(ManualDispatchStrategy::class, $strategy);
        $this->assertNull($strategy->assign(
            new Job,
            new Collection,
        ));
    }

    public function test_register_custom_strategy(): void
    {
        $registry = $this->app->make(DispatchStrategyRegistry::class);

        $registry->register('test-custom', TestDispatchStrategy::class);

        $strategies = $registry->availableStrategies();
        $this->assertArrayHasKey('test-custom', $strategies);
        $this->assertSame('Test Strategy', $strategies['test-custom']['name']);
    }

    public function test_active_slug_falls_back_to_manual_when_configured_strategy_missing(): void
    {
        Setting::set('dispatch_strategy', 'nonexistent');

        $registry = $this->app->make(DispatchStrategyRegistry::class);

        $this->assertSame('manual', $registry->activeSlug());
    }

    public function test_resolve_falls_back_to_manual_when_slug_unknown(): void
    {
        Setting::set('dispatch_strategy', 'nonexistent');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'nonexistent') && str_contains($msg, 'falling back'));

        $registry = $this->app->make(DispatchStrategyRegistry::class);

        $strategy = $registry->resolve();

        $this->assertInstanceOf(ManualDispatchStrategy::class, $strategy);
    }

    public function test_assignable_drivers_filter_hook(): void
    {
        $filters = $this->app->make(FilterRegistry::class);

        $driver1 = new User(['name' => 'Driver One']);
        $driver1->id = 1;
        $driver2 = new User(['name' => 'Driver Two']);
        $driver2->id = 2;
        $driver3 = new User(['name' => 'Driver Three']);
        $driver3->id = 3;

        $drivers = new Collection([$driver1, $driver2, $driver3]);

        $filters->register('schneespur.job.assignable_drivers', function (Collection $drivers): Collection {
            return $drivers->filter(fn (User $d) => $d->id !== 2);
        }, 100);

        $filtered = $filters->apply('schneespur.job.assignable_drivers', $drivers);

        $this->assertCount(2, $filtered);
        $this->assertFalse($filtered->contains(fn (User $d) => $d->id === 2));
    }

    public function test_dispatch_settings_page_renders_for_admin(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.settings.dispatch'));

        $response->assertStatus(200);
        $response->assertSee(__('dispatch.settings_title'));
        $response->assertSee('dispatch_strategy');
    }

    public function test_dispatch_settings_update_persists_strategy(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.dispatch.update'), [
            'dispatch_strategy' => 'manual',
        ]);

        $response->assertRedirect(route('admin.settings.dispatch'));
        $this->assertSame('manual', Setting::get('dispatch_strategy'));
    }

    public function test_dispatch_settings_rejects_invalid_strategy(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.dispatch.update'), [
            'dispatch_strategy' => 'nonexistent_strategy',
        ]);

        $response->assertSessionHasErrors('dispatch_strategy');
    }

    public function test_settings_index_shows_dispatch_card(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));

        $response->assertStatus(200);
        $response->assertSee(__('dispatch.settings_title'));
    }
}

class TestDispatchStrategy implements DispatchStrategyInterface
{
    public function assign(Job $job, Collection $drivers): ?User
    {
        return $drivers->first();
    }

    public function canHandle(Job $job): bool
    {
        return true;
    }

    public function label(): string
    {
        return 'Test Strategy';
    }
}
