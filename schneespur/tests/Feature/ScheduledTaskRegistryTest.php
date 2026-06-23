<?php

namespace Tests\Feature;

use App\Services\Scheduler\ScheduledTaskInterface;
use App\Services\Scheduler\ScheduledTaskRegistry;
use App\Services\Scheduler\Tasks\CronHeartbeatTask;
use App\Services\Scheduler\Tasks\PurgeModuleLogsTask;
use App\Services\Scheduler\Tasks\QueueWorkTask;
use App\Services\Scheduler\Tasks\RetentionDeleteTask;
use App\Services\Scheduler\Tasks\UpdateCheckTask;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduledTaskRegistryTest extends TestCase
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

    public function test_registry_is_singleton(): void
    {
        $a = $this->app->make(ScheduledTaskRegistry::class);
        $b = $this->app->make(ScheduledTaskRegistry::class);

        $this->assertSame($a, $b);
    }

    public function test_all_core_tasks_are_registered(): void
    {
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        $this->assertTrue($registry->has('retention-delete'));
        $this->assertTrue($registry->has('update-check'));
        $this->assertTrue($registry->has('queue-work'));
        $this->assertTrue($registry->has('cron-heartbeat'));
        $this->assertTrue($registry->has('purge-module-logs'));
        $this->assertCount(5, $registry->all());
    }

    public function test_resolve_returns_task_instances(): void
    {
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        $this->assertInstanceOf(RetentionDeleteTask::class, $registry->resolve('retention-delete'));
        $this->assertInstanceOf(UpdateCheckTask::class, $registry->resolve('update-check'));
        $this->assertInstanceOf(QueueWorkTask::class, $registry->resolve('queue-work'));
        $this->assertInstanceOf(CronHeartbeatTask::class, $registry->resolve('cron-heartbeat'));
        $this->assertInstanceOf(PurgeModuleLogsTask::class, $registry->resolve('purge-module-logs'));
    }

    public function test_resolve_unknown_slug_returns_null(): void
    {
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        $this->assertNull($registry->resolve('nonexistent'));
    }

    public function test_core_tasks_are_all_enabled(): void
    {
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        $enabled = $registry->enabledTasks();

        $this->assertCount(5, $enabled);
        foreach ($enabled as $task) {
            $this->assertTrue($task->isEnabled());
        }
    }

    public function test_enabled_tasks_filters_disabled(): void
    {
        $registry = new ScheduledTaskRegistry($this->app);
        $registry->register('enabled', FakeEnabledTask::class);
        $registry->register('disabled', FakeDisabledTask::class);

        $enabled = $registry->enabledTasks();

        $this->assertCount(1, $enabled);
        $this->assertArrayHasKey('enabled', $enabled);
        $this->assertArrayNotHasKey('disabled', $enabled);
    }

    public function test_record_run_writes_to_database(): void
    {
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        $registry->recordRun('retention-delete', 'success', null, 150);

        $this->assertDatabaseHas('scheduled_task_runs', [
            'slug' => 'retention-delete',
            'status' => 'success',
            'error_message' => null,
            'duration_ms' => 150,
        ]);
    }

    public function test_record_run_stores_error_message_on_failure(): void
    {
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        $registry->recordRun('update-check', 'failed', 'Connection timed out', 5000);

        $this->assertDatabaseHas('scheduled_task_runs', [
            'slug' => 'update-check',
            'status' => 'failed',
            'error_message' => 'Connection timed out',
            'duration_ms' => 5000,
        ]);
    }

    public function test_last_run_returns_most_recent(): void
    {
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        $registry->recordRun('cron-heartbeat', 'success', null, 1);
        $registry->recordRun('cron-heartbeat', 'failed', 'error', 2);
        $registry->recordRun('cron-heartbeat', 'success', null, 3);

        $last = $registry->lastRun('cron-heartbeat');

        $this->assertNotNull($last);
        $this->assertSame('success', $last->status);
        $this->assertEquals(3, $last->duration_ms);
    }

    public function test_last_run_returns_null_when_no_runs(): void
    {
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        $this->assertNull($registry->lastRun('never-ran'));
    }

    public function test_all_with_status_merges_tasks_and_runs(): void
    {
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        $registry->recordRun('retention-delete', 'success', null, 100);

        $all = $registry->allWithStatus();

        $this->assertCount(5, $all);

        $this->assertInstanceOf(ScheduledTaskInterface::class, $all['retention-delete']['task']);
        $this->assertNotNull($all['retention-delete']['last_run']);
        $this->assertSame('success', $all['retention-delete']['last_run']->status);

        $this->assertNull($all['update-check']['last_run']);
    }

    public function test_core_tasks_have_valid_slugs_and_schedules(): void
    {
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        foreach ($registry->all() as $slug => $class) {
            $task = $registry->resolve($slug);
            $this->assertNotEmpty($task->slug());
            $this->assertNotEmpty($task->label());
            $this->assertNotEmpty($task->schedule());
            $this->assertSame($slug, $task->slug());
        }
    }

    public function test_cron_heartbeat_task_updates_cache(): void
    {
        cache()->forget('cron.last_run');

        $task = new CronHeartbeatTask;
        $task->handle();

        $this->assertNotNull(cache()->get('cron.last_run'));
    }

    public function test_module_can_register_custom_task(): void
    {
        $registry = $this->app->make(ScheduledTaskRegistry::class);

        $registry->register('fake-module-task', FakeEnabledTask::class);

        $this->assertTrue($registry->has('fake-module-task'));
        $task = $registry->resolve('fake-module-task');
        $this->assertInstanceOf(FakeEnabledTask::class, $task);
        $this->assertCount(6, $registry->all());
    }
}

class FakeEnabledTask implements ScheduledTaskInterface
{
    public function slug(): string
    {
        return 'enabled';
    }

    public function label(): string
    {
        return 'Fake Enabled Task';
    }

    public function schedule(): string
    {
        return '0 * * * *';
    }

    public function handle(): void {}

    public function isEnabled(): bool
    {
        return true;
    }

    public function source(): string
    {
        return 'module';
    }
}

class FakeDisabledTask implements ScheduledTaskInterface
{
    public function slug(): string
    {
        return 'disabled';
    }

    public function label(): string
    {
        return 'Fake Disabled Task';
    }

    public function schedule(): string
    {
        return '0 * * * *';
    }

    public function handle(): void {}

    public function isEnabled(): bool
    {
        return false;
    }

    public function source(): string
    {
        return 'module';
    }
}
