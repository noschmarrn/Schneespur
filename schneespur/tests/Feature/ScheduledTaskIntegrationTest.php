<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Services\Scheduler\ScheduledTaskRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\Stubs\FakeCleanupTask;
use Tests\TestCase;

class ScheduledTaskIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ScheduledTaskRegistry $registry;

    private FakeCleanupTask $fakeTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->app->make(ScheduledTaskRegistry::class);
        $this->fakeTask = new FakeCleanupTask;
        $this->app->instance(FakeCleanupTask::class, $this->fakeTask);
        $this->registry->register('fake-cleanup', FakeCleanupTask::class);

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

    public function test_registered_module_task_appears_in_all_with_status(): void
    {
        $tasks = $this->registry->allWithStatus();

        $this->assertArrayHasKey('fake-cleanup', $tasks);
        $this->assertSame('Test Module Cleanup', $tasks['fake-cleanup']['task']->label());
        $this->assertSame('module', $tasks['fake-cleanup']['task']->source());
        $this->assertNull($tasks['fake-cleanup']['last_run']);
    }

    public function test_successful_handle_records_run_with_success(): void
    {
        $task = $this->registry->resolve('fake-cleanup');
        $start = hrtime(true);
        $task->handle();
        $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

        $this->registry->recordRun('fake-cleanup', 'success', null, $durationMs);

        $lastRun = $this->registry->lastRun('fake-cleanup');
        $this->assertNotNull($lastRun);
        $this->assertSame('success', $lastRun->status);
        $this->assertNull($lastRun->error_message);
    }

    public function test_failed_handle_records_run_with_error(): void
    {
        $this->fakeTask->shouldThrow = true;
        $this->fakeTask->throwMessage = 'Disk full';

        $task = $this->registry->resolve('fake-cleanup');

        try {
            $task->handle();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('Disk full', $e->getMessage());
        }

        $this->registry->recordRun('fake-cleanup', 'failed', 'Disk full', 42);

        $lastRun = $this->registry->lastRun('fake-cleanup');
        $this->assertNotNull($lastRun);
        $this->assertSame('failed', $lastRun->status);
        $this->assertSame('Disk full', $lastRun->error_message);
    }

    public function test_admin_sees_fake_cleanup_task_in_crontasks_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.crontasks.index'));

        $response->assertOk();
        $response->assertSee('Test Module Cleanup');
        $response->assertSee('fake-cleanup');
    }

    public function test_admin_can_disable_module_task(): void
    {
        Setting::set('scheduled_task.fake-cleanup.enabled', '1');

        $this->assertTrue($this->fakeTask->isEnabled());

        $enabledBefore = $this->registry->enabledTasks();
        $this->assertArrayHasKey('fake-cleanup', $enabledBefore);

        $admin = $this->createAdmin();
        $response = $this->actingAs($admin)->post(route('admin.crontasks.toggle', 'fake-cleanup'));
        $response->assertRedirect(route('admin.crontasks.index'));

        $this->assertSame('0', Setting::get('scheduled_task.fake-cleanup.enabled'));

        $enabledAfter = $this->registry->enabledTasks();
        $this->assertArrayNotHasKey('fake-cleanup', $enabledAfter);
    }

    public function test_admin_can_re_enable_module_task(): void
    {
        Setting::set('scheduled_task.fake-cleanup.enabled', '0');

        $this->assertFalse($this->fakeTask->isEnabled());

        $admin = $this->createAdmin();
        $response = $this->actingAs($admin)->post(route('admin.crontasks.toggle', 'fake-cleanup'));
        $response->assertRedirect(route('admin.crontasks.index'));

        $this->assertSame('1', Setting::get('scheduled_task.fake-cleanup.enabled'));

        $enabledAfter = $this->registry->enabledTasks();
        $this->assertArrayHasKey('fake-cleanup', $enabledAfter);
    }

    public function test_core_task_toggle_returns_403(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.crontasks.toggle', 'retention-delete'));

        $response->assertForbidden();
    }

    public function test_error_isolation_other_tasks_unaffected_by_failing_task(): void
    {
        $this->fakeTask->shouldThrow = true;
        $this->fakeTask->throwMessage = 'Module exploded';

        $coreSlugs = ['retention-delete', 'update-check', 'queue-work', 'cron-heartbeat'];
        foreach ($coreSlugs as $slug) {
            $task = $this->registry->resolve($slug);
            $this->assertNotNull($task, "Core task '{$slug}' should be registered");
        }

        $task = $this->registry->resolve('fake-cleanup');
        $start = hrtime(true);
        try {
            $task->handle();
            $this->registry->recordRun('fake-cleanup', 'success', null, 0);
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);
            $this->registry->recordRun('fake-cleanup', 'failed', $e->getMessage(), $durationMs);
        }

        $fakeRun = $this->registry->lastRun('fake-cleanup');
        $this->assertSame('failed', $fakeRun->status);
        $this->assertSame('Module exploded', $fakeRun->error_message);

        $heartbeat = $this->registry->resolve('cron-heartbeat');
        $start = hrtime(true);
        $heartbeat->handle();
        $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);
        $this->registry->recordRun('cron-heartbeat', 'success', null, $durationMs);

        $heartbeatRun = $this->registry->lastRun('cron-heartbeat');
        $this->assertSame('success', $heartbeatRun->status);
    }

    public function test_multiple_runs_tracked_last_run_returns_most_recent(): void
    {
        $this->registry->recordRun('fake-cleanup', 'success', null, 100);
        $this->registry->recordRun('fake-cleanup', 'failed', 'Timeout', 5000);

        $lastRun = $this->registry->lastRun('fake-cleanup');
        $this->assertSame('failed', $lastRun->status);
        $this->assertSame('Timeout', $lastRun->error_message);
    }

    public function test_disabled_module_task_excluded_from_enabled_tasks(): void
    {
        Setting::set('scheduled_task.fake-cleanup.enabled', '0');

        $enabled = $this->registry->enabledTasks();

        $this->assertArrayNotHasKey('fake-cleanup', $enabled);
        $this->assertArrayHasKey('retention-delete', $enabled);
    }

    public function test_module_task_shows_module_source_badge_in_admin(): void
    {
        $admin = $this->createAdmin();
        $this->registry->recordRun('fake-cleanup', 'success', null, 75);

        $response = $this->actingAs($admin)->get(route('admin.crontasks.index'));

        $response->assertOk();
        $response->assertSee('Test Module Cleanup');
        $response->assertSee(__('crontasks.source_module'));
    }
}
