<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Enums\UserRole;
use App\Services\Scheduler\ScheduledTaskInterface;
use App\Services\Scheduler\ScheduledTaskRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCronTaskTest extends TestCase
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
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user->fresh();
    }

    private function createDriver(): User
    {
        $user = User::create([
            'name' => 'Driver User',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Driver;
        $user->save();

        return $user->fresh();
    }

    public function test_admin_can_view_crontasks_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.crontasks.index'));

        $response->assertOk();
        $response->assertViewIs('admin.crontasks.index');
        $response->assertViewHas('tasks');
    }

    public function test_driver_cannot_access_crontasks(): void
    {
        $driver = $this->createDriver();

        $response = $this->actingAs($driver)->get(route('admin.crontasks.index'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_crontasks_page_shows_all_core_tasks(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.crontasks.index'));

        $response->assertOk();
        $response->assertSee('retention-delete');
        $response->assertSee('update-check');
        $response->assertSee('queue-work');
        $response->assertSee('cron-heartbeat');
    }

    public function test_crontasks_page_shows_last_run_status(): void
    {
        $admin = $this->createAdmin();
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        $registry->recordRun('retention-delete', 'success', null, 150);

        $response = $this->actingAs($admin)->get(route('admin.crontasks.index'));

        $response->assertOk();
        $response->assertSee('150');
    }

    public function test_crontasks_page_shows_failed_task_error(): void
    {
        $admin = $this->createAdmin();
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        $registry->recordRun('update-check', 'failed', 'Connection timed out', 5000);

        $response = $this->actingAs($admin)->get(route('admin.crontasks.index'));

        $response->assertOk();
        $response->assertSee('Connection timed out');
    }

    public function test_toggle_returns_403_for_core_tasks(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.crontasks.toggle', 'retention-delete'));

        $response->assertForbidden();
    }

    public function test_toggle_returns_404_for_unknown_slug(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.crontasks.toggle', 'nonexistent'));

        $response->assertNotFound();
    }

    public function test_toggle_disables_module_task(): void
    {
        $admin = $this->createAdmin();
        $registry = $this->app->make(ScheduledTaskRegistry::class);
        $registry->register('fake-module', FakeModuleTask::class);

        Setting::set('scheduled_task.fake-module.enabled', '1');

        $response = $this->actingAs($admin)->post(route('admin.crontasks.toggle', 'fake-module'));

        $response->assertRedirect(route('admin.crontasks.index'));
        $this->assertSame('0', Setting::get('scheduled_task.fake-module.enabled'));
    }

    public function test_toggle_enables_module_task(): void
    {
        $admin = $this->createAdmin();
        $registry = $this->app->make(ScheduledTaskRegistry::class);
        $registry->register('fake-module', FakeModuleTask::class);

        Setting::set('scheduled_task.fake-module.enabled', '0');

        $response = $this->actingAs($admin)->post(route('admin.crontasks.toggle', 'fake-module'));

        $response->assertRedirect(route('admin.crontasks.index'));
        $this->assertSame('1', Setting::get('scheduled_task.fake-module.enabled'));
    }

    public function test_core_tasks_report_core_source(): void
    {
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        foreach (['retention-delete', 'update-check', 'queue-work', 'cron-heartbeat'] as $slug) {
            $task = $registry->resolve($slug);
            $this->assertSame('core', $task->source());
        }
    }

    public function test_crontasks_page_shows_core_badge(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.crontasks.index'));

        $response->assertOk();
        $response->assertSee(__('crontasks.source_core'));
    }

    public function test_guest_cannot_access_crontasks(): void
    {
        $response = $this->get(route('admin.crontasks.index'));

        $response->assertRedirect(route('login'));
    }
}

class FakeModuleTask implements ScheduledTaskInterface
{
    public function slug(): string
    {
        return 'fake-module';
    }

    public function label(): string
    {
        return 'Fake Module Task';
    }

    public function schedule(): string
    {
        return '0 * * * *';
    }

    public function handle(): void {}

    public function isEnabled(): bool
    {
        return Setting::get('scheduled_task.fake-module.enabled', '1') === '1';
    }

    public function source(): string
    {
        return 'module';
    }
}
